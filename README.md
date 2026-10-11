# Tarea-2-BDD

Integrantes: Felipe Brito Alarcón (202210034-6) y Tomás Rojas Villarroel (202210032-k)

Link Repositorio Github: https://github.com/TRojas25/Tarea-2-BDD

Instrucciones para correcta ejecución:

1. Copiar la carpeta PHP dentro de `htdocs` de XAMPP.
2. Iniciar Apache y MySQL desde el panel de XAMPP.
3. Importar el script SQL desde phpMyAdmin (`http://localhost/phpmyadmin`):
   creacion.sql
4. Abrir el sistema en: <http://localhost/PHP/>




Usuarios de prueba:

Pacientes: 22000001-1 Contraseña: Pac123#
Médicos: 12000001-1  Contraseña: Med123#
Administrador: 10000000-0 Contraseña: Adm123#



Reglas de negocio:

Al momento de crear una contraseña los requisitos mínimos son: seis caracteres en total, una mayuscula, tres numeros y un simbolo.

Si un paciente borra su cuenta, no se borrarán las citas ni atenciones de la base de datos (solamente las citas Reservadas ahora pasaran a Canceladas).
Si además este paciente quiere volver a crear su cuenta, se actualizaran los datos como su nombre o previsión de la base de datos, y se mostraran las citas y atenciones pasadas.

Supuestos:

Cuando el administrador desea quitar una especialidad o un centro a un médico que tenga citas futuras asociadas, 
el sistema lo impedirá. Para ello deberá manualmente el médico o el administrador modificar esas citas futuras asociadas. Por ejemplo cancelandolas.

Se asume que el catálogo de diagnósticos (código CIE-10 y descripción) es predefinido, fijo y finito. En caso de requerir un nuevo diagnóstico para la atención médica, este debe ser ingresado previamente en la base de datos con una clave primaria/ID fija (Código CIE-10) antes de poder ser seleccionado o asociado a una ficha de atención.
