<?php
return [
'required'=>'El campo :attribute es obligatorio.', 'email'=>'El correo no tiene un formato válido.',
'string'=>'El campo :attribute debe ser texto.', 'integer'=>'El campo :attribute debe ser entero.',
'numeric'=>'El campo :attribute debe ser numérico.', 'boolean'=>'El campo :attribute debe ser verdadero o falso.',
'in'=>'El valor de :attribute no es válido.', 'uuid'=>'El identificador de :attribute no es válido.',
'accepted'=>'Debes confirmar :attribute.', 'date'=>'La fecha :attribute no es válida.',
'after_or_equal'=>'La fecha :attribute debe ser posterior o igual a :date.',
'exists'=>'El registro de :attribute no existe.', 'unique'=>'El valor de :attribute ya está registrado.',
'confirmed'=>'La confirmación de :attribute no coincide.', 'file'=>'El campo :attribute debe ser un archivo.',
'prohibited'=>'No se permite enviar :attribute.', 'regex'=>'El formato de :attribute no es válido.',
'decimal'=>'El campo :attribute debe tener hasta dos decimales.',
'min'=>['string'=>'El campo :attribute requiere al menos :min caracteres.','numeric'=>'El campo :attribute debe ser al menos :min.','array'=>'Selecciona al menos :min elementos.','file'=>'El archivo es demasiado pequeño.'],
'max'=>['string'=>'El campo :attribute admite hasta :max caracteres.','numeric'=>'El campo :attribute no puede superar :max.','array'=>'Demasiados elementos en :attribute.','file'=>'El archivo es demasiado grande.'],
'attributes'=>['from'=>'desde','to'=>'hasta','call_id'=>'convocatoria','page'=>'página','email'=>'correo','password'=>'contraseña','name'=>'nombre','amount'=>'importe','total'=>'total','count'=>'cuotas','confirmed'=>'la simulación','q'=>'búsqueda','file'=>'archivo','faculty'=>'facultad','career'=>'carrera'],
];
