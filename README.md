# Sistema de Intranet Institucional

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)

Un sistema web integral diseñado para la gestión interna institucional. Permite la administración de usuarios, control de asistencia (marcaciones), reserva de horas y la estructuración de unidades y departamentos.

## Características Principales

El sistema está dividido en varios módulos clave:

* **Autenticación y Seguridad:**
    * Inicio de sesión seguro con protección CSRF.
    * Gestión de sesiones y encriptación de contraseñas (`hash.php`).
    * Control de acceso basado en roles y permisos (`rol_permisos.php`).
* **Gestión Organizacional:**
    * Administración de Direcciones, Unidades y Cargos.
    * Mantenedores completos (CRUD) para la estructura de la institución.
* **Control de Asistencia (Marcaciones):**
    * Importación masiva de registros de asistencia.
    * Cruce y validación de marcaciones por funcionario.
    * Visualización de marcaciones personales (`mi.php`) y gestión de excepciones.
* **Reserva y Solicitud de Horas:**
    * Panel de control (Dashboard) para la administración de horarios.
    * Generación de "slots" o bloques de atención.
    * Interfaz para que los usuarios reserven y soliciten horas.
* **Gestión de Actividades:**
    * Registro y listado de actividades internas institucionales.

## Estructura del Proyecto

```text
Intranet/
├── admin/                  # Panel de administración (Módulos, CRUDs, Configuración)
│   ├── marcaciones/        # Lógica de importación y validación de asistencia
│   └── ...                 # Vistas de gestión (Cargos, Direcciones, Unidades)
├── inc/                    # Archivos de configuración (BD, Autenticación, CSRF, Helpers)
├── marcaciones/            # Vistas de asistencia para el usuario final
├── static/                 # Recursos estáticos
│   ├── css/                # Hojas de estilo estructuradas por módulo
│   └── img/                # Logos e imágenes (ORIGINALN.png, logo.png)
├── dashboard.php           # Panel principal post-login
├── login.php / logout.php  # Control de acceso
└── GUIA.txt                # Documentación interna/notas del desarrollador
```

## Instalación y Configuración
Sigue estos pasos para desplegar el proyecto en un entorno local (como XAMPP, WAMP o Docker):

1. **Clonar el repositorio:**

   ```bash
   git clone [https://github.com/elJulioDev/intranet.git](https://github.com/elJulioDev/intranet.git)
   cd intranet
   ```
   
3. **Configurar la Base de Datos:**
   * Crea una base de datos MySQL/MariaDB.
   * Importa el archivo `.sql` de la estructura (si está disponible).
   * Renombra o edita el archivo de conexión en `inc/db.php` con tus credenciales locales:

     ```php
     // Ejemplo de inc/db.php
     $host = 'localhost';
     $user = 'root';
     $pass = '';
     $dbname = 'nombre_de_tu_bd';
     ```

4. **Crear el Administrador Inicial:**
   * Navega a `http://localhost/intranet/crear_admin.php` en tu navegador para generar el primer usuario con privilegios de administrador.
   * **Importante:** Por razones de seguridad, elimina o renombra el archivo `crear_admin.php` después de usarlo en producción.

5. **Iniciar Sesión:**
   * Ingresa a `http://localhost/intranet/login.php` con las credenciales recién creadas.

## Tecnologías Utilizadas

* **Backend:** PHP 5.6 puro (Vanilla) con arquitectura modular.
* **Frontend:** HTML5, CSS3 (Estilos modulares en `static/css/`).
* **Base de Datos:** MySQL / MariaDB.
