-- =====================================================
--  monchomania - Esquema de Base de Datos (MySQL / MariaDB)
--  Importar en phpMyAdmin de Hostinger (pestaña "Importar")
--
--  Credenciales iniciales del Superadministrador:
--     Email:    admin@monchomania.com
--     Password: admin123   (cámbiala tras el primer acceso)
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `monchomania`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `monchomania`;

-- -----------------------------------------------------
-- 1. Tabla: roles
-- -----------------------------------------------------
DROP TABLE IF EXISTS `permisos_modulos`;
DROP TABLE IF EXISTS `noticias_comentarios`;
DROP TABLE IF EXISTS `cumple_comentarios`;
DROP TABLE IF EXISTS `noticias`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `modulos`;
DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_rol` VARCHAR(50)  NOT NULL COMMENT 'superadmin | usuario',
  `creado_en`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_nombre` (`nombre_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Tabla: usuarios
-- -----------------------------------------------------
CREATE TABLE `usuarios` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_rol`           INT UNSIGNED NOT NULL DEFAULT 2,
  `nombre`           VARCHAR(80)  NOT NULL,
  `apellidos`        VARCHAR(120) NOT NULL,
  `fecha_nacimiento` DATE         NOT NULL,
  `direccion`        VARCHAR(255) DEFAULT NULL,
  `barrio`           VARCHAR(120) DEFAULT NULL,
  `zona`             ENUM('Urbana','Vereda') NOT NULL DEFAULT 'Urbana',
  `foto`             VARCHAR(255) DEFAULT NULL COMMENT 'Ruta relativa de la imagen de perfil',
  `email`            VARCHAR(160) NOT NULL,
  `password`         VARCHAR(255) NOT NULL COMMENT 'Hash generado con password_hash()',
  `estado`           TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=Activo, 2=Inactivo',
  `creado_en`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  KEY `idx_usuarios_rol` (`id_rol`),
  KEY `idx_usuarios_nacimiento` (`fecha_nacimiento`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`)
    REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Tabla: modulos
-- -----------------------------------------------------
CREATE TABLE `modulos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(120) NOT NULL,
  `clave_modulo`  VARCHAR(60)  NOT NULL,
  `descripcion`   VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modulos_clave` (`clave_modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4. Tabla: permisos_modulos (visibilidad por rol)
-- -----------------------------------------------------
CREATE TABLE `permisos_modulos` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_rol`    INT UNSIGNED NOT NULL,
  `id_modulo` INT UNSIGNED NOT NULL,
  `visible`   TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permisos_rol_modulo` (`id_rol`, `id_modulo`),
  CONSTRAINT `fk_permisos_rol` FOREIGN KEY (`id_rol`)
    REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_permisos_modulo` FOREIGN KEY (`id_modulo`)
    REFERENCES `modulos` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 5. Tabla: cumple_comentarios (saludos de cumpleaños)
-- -----------------------------------------------------
CREATE TABLE `cumple_comentarios` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario_destino` INT UNSIGNED NOT NULL COMMENT 'Persona que cumple años',
  `id_usuario_autor`   INT UNSIGNED NOT NULL COMMENT 'Persona que escribe el saludo',
  `comentario`         TEXT         NOT NULL,
  `fecha_creacion`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cumple_destino` (`id_usuario_destino`),
  CONSTRAINT `fk_cumple_destino` FOREIGN KEY (`id_usuario_destino`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_cumple_autor` FOREIGN KEY (`id_usuario_autor`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 6. Tabla: noticias (publicaciones del superadmin)
-- -----------------------------------------------------
CREATE TABLE `noticias` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`          VARCHAR(200) NOT NULL,
  `tipo`            ENUM('imagen','video','cancion','frase') NOT NULL DEFAULT 'frase',
  `contenido_texto` TEXT         DEFAULT NULL,
  `url_media`       VARCHAR(500) DEFAULT NULL COMMENT 'Ruta de imagen subida o URL de video/audio',
  `id_autor`        INT UNSIGNED NOT NULL,
  `fecha_creacion`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_noticias_autor` (`id_autor`),
  CONSTRAINT `fk_noticias_autor` FOREIGN KEY (`id_autor`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 7. Tabla: noticias_comentarios
-- -----------------------------------------------------
CREATE TABLE `noticias_comentarios` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_noticia`     INT UNSIGNED NOT NULL,
  `id_usuario`     INT UNSIGNED NOT NULL,
  `comentario`     TEXT         NOT NULL,
  `fecha_creacion` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nc_noticia` (`id_noticia`),
  CONSTRAINT `fk_nc_noticia` FOREIGN KEY (`id_noticia`)
    REFERENCES `noticias` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_nc_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  DATOS INICIALES (Semilla)
-- =====================================================

-- Roles por defecto
INSERT INTO `roles` (`id`, `nombre_rol`) VALUES
  (1, 'superadmin'),
  (2, 'usuario');

-- Superadministrador por defecto
-- email: admin@monchomania.com  |  password: admin123
INSERT INTO `usuarios`
  (`id`, `id_rol`, `nombre`, `apellidos`, `fecha_nacimiento`, `direccion`, `barrio`, `zona`, `foto`, `email`, `password`, `estado`)
VALUES
  (1, 1, 'Administrador', 'Monchomania', '1990-01-01', 'Sede Principal', 'Centro', 'Urbana', NULL,
   'admin@monchomania.com', '$2y$10$ejgLHPSeRSIAH/84008xMORKGta.wbojecy1nRGW93mOK3jQgRNIK', 1);

-- Módulos base registrados
INSERT INTO `modulos` (`id`, `nombre`, `clave_modulo`, `descripcion`) VALUES
  (1, 'Perfil',                    'perfil',        'Información personal y foto del usuario'),
  (2, 'Calendario de Cumpleaños',  'calendario',    'Calendario interactivo con los cumpleaños de la comunidad'),
  (3, 'Noticias',                  'noticias',      'Publicaciones de la comunidad (imágenes, videos, canciones y frases)'),
  (4, 'Configuración de Módulos',  'configuracion', 'Panel para activar/desactivar módulos'),
  (5, 'Gestión de Usuarios',       'usuarios',      'Administración de los usuarios de la plataforma');

-- Permisos del Superadministrador (rol 1): todos los módulos visibles
INSERT INTO `permisos_modulos` (`id_rol`, `id_modulo`, `visible`) VALUES
  (1, 1, 1), (1, 2, 1), (1, 3, 1), (1, 4, 1), (1, 5, 1);

-- Permisos del Usuario normal (rol 2): Perfil, Calendario y Noticias visibles
INSERT INTO `permisos_modulos` (`id_rol`, `id_modulo`, `visible`) VALUES
  (2, 1, 1), (2, 2, 1), (2, 3, 1), (2, 4, 0), (2, 5, 0);

SET FOREIGN_KEY_CHECKS = 1;
