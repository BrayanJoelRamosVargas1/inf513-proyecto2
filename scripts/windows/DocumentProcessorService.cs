using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Runtime.InteropServices;
using System.ServiceProcess;
using System.Text.RegularExpressions;
using System.Threading;
using System.Web.Script.Serialization;

// SCM adapter only: academic decisions, queue leases and scans remain in Laravel.
public sealed class ProcessorService : ServiceBase
{
    private readonly string configPath;
    private readonly bool console;
    private readonly object gate = new object();
    private Thread thread;
    private volatile bool stopping;
    private Process child;
    private FileStream owner;
    private IntPtr job;
    private Settings settings;
    private string stateDir;

    public sealed class Settings
    {
        public string service_name { get; set; }
        public string root { get; set; }
        public string python { get; set; }
        public string php { get; set; }
        public string worker_name { get; set; }
        public Dictionary<string, string> environment { get; set; }
    }

    private ProcessorService(string path, bool interactive)
    {
        configPath = Path.GetFullPath(path);
        console = interactive;
        settings = new JavaScriptSerializer().Deserialize<Settings>(File.ReadAllText(configPath));
        if (settings == null || !SafeName(settings.service_name) || !SafeName(settings.worker_name))
            throw new InvalidDataException("Invalid service or worker name.");
        foreach (string pathValue in new[] { settings.root, settings.python, settings.php })
            if (String.IsNullOrWhiteSpace(pathValue) || !Path.IsPathRooted(pathValue) || pathValue.Contains("\""))
                throw new InvalidDataException("Absolute, unquoted paths required.");
        settings.root = Path.GetFullPath(settings.root);
        if (!File.Exists(settings.python) || !File.Exists(settings.php) ||
            !File.Exists(Path.Combine(settings.root, "artisan")) ||
            !File.Exists(Path.Combine(settings.root, "scripts", "run_document_processor.py")))
            throw new InvalidDataException("Configured runtime is incomplete.");
        ServiceName = settings.service_name;
        CanStop = true;
        CanShutdown = true;
        AutoLog = false;
        stateDir = Path.Combine(settings.root, ".local", "document-processor");
    }

    private static bool SafeName(string text)
    {
        return text != null && Regex.IsMatch(text, "^[A-Za-z0-9_-]{1,40}$");
    }

    private string OwnFile(string suffix) { return Path.Combine(stateDir, settings.worker_name + suffix); }

    private void Record(string state, int? pid, int restarts)
    {
        var payload = new Dictionary<string, object> {
            { "time_utc", DateTime.UtcNow.ToString("o") }, { "state", state },
            { "host_pid", Process.GetCurrentProcess().Id }, { "supervisor_pid", pid },
            { "restarts", restarts }, { "mode", console ? "console-test" : "windows-service" }
        };
        string serialized = new JavaScriptSerializer().Serialize(payload);
        lock (gate)
        {
            string temp = OwnFile(".host.tmp"), target = OwnFile(".host.json");
            File.WriteAllText(temp, serialized);
            for (int attempt = 0; attempt < 5; attempt++) {
                try {
                    if (File.Exists(target)) File.Replace(temp, target, null);
                    else File.Move(temp, target);
                    break;
                } catch (IOException e) {
                    int code = e.HResult & 0xffff;
                    if (code != 32 && code != 33) throw;
                    // A status reader must not terminate the managed worker.
                    Thread.Sleep(20);
                }
            }
        }
    }

    protected override void OnStart(string[] args)
    {
        Directory.CreateDirectory(stateDir);
        // A file lock works across service sessions and user identities too.
        owner = new FileStream(OwnFile(".host.lock"), FileMode.OpenOrCreate, FileAccess.ReadWrite, FileShare.None);
        try
        {
            File.Delete(OwnFile(".host.stop"));
            File.Delete(OwnFile(".stop"));
            job = Native.CreateKillJob();
            stopping = false;
            thread = new Thread(RunLoop) { IsBackground = true };
            thread.Start();
        }
        catch { owner.Dispose(); owner = null; throw; }
    }

    private void RunLoop()
    {
        int restarts = 0, failures = 0;
        try
        {
            while (!stopping && !File.Exists(OwnFile(".host.stop")))
            {
                File.Delete(OwnFile(".stop"));
                var info = new ProcessStartInfo(settings.python,
                    "\"" + Path.Combine(settings.root, "scripts", "run_document_processor.py") +
                    "\" --name " + settings.worker_name + " --idle-seconds 5");
                info.WorkingDirectory = settings.root;
                info.UseShellExecute = false;
                info.CreateNoWindow = true;
                if (settings.environment != null)
                    foreach (var item in settings.environment) info.EnvironmentVariables[item.Key] = item.Value;
                info.EnvironmentVariables["PATH"] = Path.GetDirectoryName(settings.php) + ";" + info.EnvironmentVariables["PATH"];
                // Child starts suspended and joins the kill-on-close job before executing.
                // No scanner/worker can escape during the assignment window.
                lock (gate) { child = Native.StartInJob(info, job); }
                Record("running", child.Id, restarts);
                DateTime started = DateTime.UtcNow;
                DateTime heartbeat = started;
                while (!stopping && !File.Exists(OwnFile(".host.stop")) && !child.WaitForExit(200))
                    if ((DateTime.UtcNow - heartbeat).TotalSeconds >= 2) {
                        Record("running", child.Id, restarts); heartbeat = DateTime.UtcNow;
                    }
                if (stopping || File.Exists(OwnFile(".host.stop"))) break;
                failures = (DateTime.UtcNow - started).TotalSeconds > 60 ? 0 : failures + 1;
                restarts++;
                Record("supervisor_exited", null, restarts);
                lock (gate) {
                    child.Dispose(); child = null;
                    Native.CloseHandle(job); job = Native.CreateKillJob();
                }
                int wait = Math.Min(30000, 1000 * (1 << Math.Min(failures, 5)));
                for (int elapsed = 0; elapsed < wait && !stopping && !File.Exists(OwnFile(".host.stop")); elapsed += 200)
                    Thread.Sleep(200);
            }
            if (!console && !stopping) ThreadPool.QueueUserWorkItem(delegate { Stop(); });
        }
        catch (Exception)
        {
            // No environment, credentials, exception paths or document contents in status.
            try { Record("host_failed", null, restarts); } catch { }
            // SCM recovery requires failure, not a falsely successful stopped service.
            if (!stopping) Environment.Exit(2);
        }
    }

    protected override void OnStop()
    {
        stopping = true;
        if (!console) RequestAdditionalTime(25000);
        File.WriteAllText(OwnFile(".stop"), "stop");
        if (thread != null) thread.Join(5000);
        Process remaining;
        lock (gate) { remaining = child; }
        if (remaining != null && !remaining.HasExited) remaining.WaitForExit(10000);
        // Closes only this host's job, including a worker orphaned by its supervisor.
        if (job != IntPtr.Zero) { Native.CloseHandle(job); job = IntPtr.Zero; }
        if (remaining != null) { remaining.WaitForExit(5000); remaining.Dispose(); child = null; }
        Record("stopped", null, 0);
        if (owner != null) { owner.Dispose(); owner = null; }
    }

    protected override void OnShutdown() { OnStop(); }

    public static int Main(string[] args)
    {
        try
        {
            if (args.Length < 1 || args.Length > 2 || (args.Length == 2 && args[1] != "--console")) return 64;
            var service = new ProcessorService(args[0], args.Length == 2);
            if (!service.console) { Run(service); return 0; }
            service.OnStart(new string[0]);
            while (!File.Exists(service.OwnFile(".host.stop"))) Thread.Sleep(200);
            service.OnStop();
            return 0;
        }
        catch (Exception) { Console.Error.WriteLine("Service configuration or ownership rejected."); return 1; }
    }

    private static class Native
    {
        [StructLayout(LayoutKind.Sequential)] private struct IO_COUNTERS { public ulong a,b,c,d,e,f; }
        [StructLayout(LayoutKind.Sequential)] private struct BASIC_LIMIT {
            public long processTime, jobTime; public uint flags; public UIntPtr min,max; public uint active;
            public UIntPtr affinity; public uint priority,scheduling;
        }
        [StructLayout(LayoutKind.Sequential)] private struct EXTENDED_LIMIT {
            public BASIC_LIMIT basic; public IO_COUNTERS io; public UIntPtr processMemory,jobMemory,peakProcess,peakJob;
        }
        [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Unicode)] private struct STARTUPINFO {
            public uint cb; public string reserved,desktop,title; public uint x,y,xSize,ySize,xCount,yCount,fill,flags;
            public ushort show,cbReserved; public IntPtr reserved2,input,output,error;
        }
        [StructLayout(LayoutKind.Sequential)] private struct PROCESS_INFORMATION { public IntPtr process,thread; public uint pid,tid; }
        [DllImport("kernel32.dll", CharSet=CharSet.Unicode, SetLastError=true)] private static extern IntPtr CreateJobObject(IntPtr security,string name);
        [DllImport("kernel32.dll", SetLastError=true)] private static extern bool SetInformationJobObject(IntPtr job,int kind,ref EXTENDED_LIMIT value,uint length);
        [DllImport("kernel32.dll", SetLastError=true)] private static extern bool AssignProcessToJobObject(IntPtr job,IntPtr process);
        [DllImport("kernel32.dll", CharSet=CharSet.Unicode, SetLastError=true)] private static extern bool CreateProcess(string app,string command,IntPtr psa,IntPtr tsa,bool inherit,uint flags,IntPtr env,string cwd,ref STARTUPINFO startup,out PROCESS_INFORMATION process);
        [DllImport("kernel32.dll", SetLastError=true)] private static extern uint ResumeThread(IntPtr thread);
        [DllImport("kernel32.dll", SetLastError=true)] private static extern bool TerminateProcess(IntPtr process,uint code);
        [DllImport("kernel32.dll")] public static extern bool CloseHandle(IntPtr handle);

        public static IntPtr CreateKillJob()
        {
            IntPtr result = CreateJobObject(IntPtr.Zero, null);
            var limit = new EXTENDED_LIMIT(); limit.basic.flags = 0x2000; // KILL_ON_JOB_CLOSE
            if (result == IntPtr.Zero || !SetInformationJobObject(result,9,ref limit,(uint)Marshal.SizeOf(limit)))
            { if(result!=IntPtr.Zero)CloseHandle(result); throw new IOException("Job setup failed."); }
            return result;
        }

        public static Process StartInJob(ProcessStartInfo info, IntPtr job)
        {
            var entries = new List<string>();
            foreach (string key in info.EnvironmentVariables.Keys) entries.Add(key + "=" + info.EnvironmentVariables[key]);
            entries.Sort(StringComparer.OrdinalIgnoreCase);
            IntPtr env = Marshal.StringToHGlobalUni(String.Join("\0",entries.ToArray()) + "\0\0");
            var startup = new STARTUPINFO(); startup.cb=(uint)Marshal.SizeOf(startup);
            PROCESS_INFORMATION pi;
            try
            {
                if (!CreateProcess(info.FileName,"\""+info.FileName+"\" "+info.Arguments,IntPtr.Zero,IntPtr.Zero,false,
                    0x00000004 | 0x08000000 | 0x00000400,env,info.WorkingDirectory,ref startup,out pi))
                    throw new IOException("Child creation failed.");
                try {
                    if (!AssignProcessToJobObject(job,pi.process))
                    { TerminateProcess(pi.process,2); throw new IOException("Child containment failed."); }
                    Process owned = Process.GetProcessById((int)pi.pid);
                    try {
                        // Pin the actual process before it can exit; do not reopen a reused PID.
                        if (owned.Handle == IntPtr.Zero || ResumeThread(pi.thread)==0xffffffff)
                            throw new IOException("Child resume failed.");
                        return owned;
                    } catch { TerminateProcess(pi.process,2); owned.Dispose(); throw; }
                } finally { CloseHandle(pi.thread); CloseHandle(pi.process); }
            }
            finally { Marshal.FreeHGlobal(env); }
        }
    }
}
