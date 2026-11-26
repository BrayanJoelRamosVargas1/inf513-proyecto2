<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $attributes = ['active' => true, 'role' => 'student'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * En la instalación real no se admiten correos de dominios reservados para pruebas (.test, .invalid, .localhost):
     * son los de las cuentas de entrenamiento y no pertenecen a una persona.
     *
     * @return list<string>
     */
    public static function reservedEmailRules(): array
    {
        return config('portal.experience') === 'institutional' ? ['not_regex:/\.(test|invalid|localhost)$/i'] : [];
    }

    /** Cédula o registro: mayúsculas, sin espacios ni puntos, para que no se repitan por formato. */
    public static function normalizeIdentifier(mixed $value): ?string
    {
        $value = preg_replace('/[^0-9A-Z]+/', '', mb_strtoupper(trim((string) $value)));

        return $value === '' ? null : $value;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }
}
