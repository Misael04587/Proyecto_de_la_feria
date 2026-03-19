# Sistema de Pasantias

Proyecto PHP/MySQL para gestionar estudiantes, empresas, evaluaciones tecnicas y asignaciones de pasantias por centro educativo.

## Estado de Fase 1

La fase base queda cubierta con estos modulos:

- CRUD de empresas desde el panel admin.
- Banco de preguntas por area tecnica.
- Flujo de pasantias con revision manual:
  examen aprobado -> revision del centro -> asignacion manual -> finalizacion o cancelacion.
- Recuperacion de contrasena por enlace temporal.
- Paneles de estudiantes, evaluaciones, areas y reportes basicos.

Nota:
- El cierre automatico por inactividad no fue implementado en esta fase.

## Requisitos

- XAMPP o entorno equivalente con `PHP 8+` y `MySQL`.
- Base de datos configurada en [app/config/database.php](/c:/xampp/htdocs/sistema_pasantias/sistema_pasantias/app/config/database.php).
- Carpeta publica servida desde `public/`.

## Arranque rapido

1. Crea o importa la base de datos `sistema_pasantias`.
2. Ajusta credenciales en [app/config/database.php](/c:/xampp/htdocs/sistema_pasantias/sistema_pasantias/app/config/database.php).
3. Levanta Apache y MySQL.
4. Abre `http://localhost/sistema_pasantias/public/index.php?page=login`.

## Recuperacion de contrasena

- Ruta publica: `index.php?page=forgot-password`.
- El usuario debe indicar `codigo de centro` y `correo`.
- El sistema genera un token temporal valido por 60 minutos.
- En `DEBUG_MODE = true`, el enlace de recuperacion se muestra en pantalla para pruebas locales.
- En produccion, ese enlace debe enviarse por correo y `DEBUG_MODE` debe cambiarse a `false`.

## Flujo actual de pasantias

1. El estudiante sube CV y se postula.
2. El sistema genera examen tecnico segun el area.
3. Si aprueba, la evaluacion queda pendiente de asignacion.
4. El admin asigna la pasantia desde `admin-evaluations`.
5. El admin puede finalizar o cancelar la pasantia desde `admin-students`.

## Archivos clave

- [app/controllers/AdminController.php](/c:/xampp/htdocs/sistema_pasantias/sistema_pasantias/app/controllers/AdminController.php)
- [app/controllers/AuthController.php](/c:/xampp/htdocs/sistema_pasantias/sistema_pasantias/app/controllers/AuthController.php)
- [app/models/Evaluacion.php](/c:/xampp/htdocs/sistema_pasantias/sistema_pasantias/app/models/Evaluacion.php)
- [app/models/Asignacion.php](/c:/xampp/htdocs/sistema_pasantias/sistema_pasantias/app/models/Asignacion.php)
- [app/models/PasswordReset.php](/c:/xampp/htdocs/sistema_pasantias/sistema_pasantias/app/models/PasswordReset.php)
