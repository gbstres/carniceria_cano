-- Migración: Permitir valores por defecto y nulos en campos no obligatorios al crear usuario en cc_users
ALTER TABLE `cc_users`
  MODIFY `color_usuario` VARCHAR(10) NOT NULL DEFAULT '',
  MODIFY `fecha_acceso` DATE NULL DEFAULT NULL,
  MODIFY `hora_acceso` TIME NULL DEFAULT NULL,
  MODIFY `id_usuario_act` INT(10) NULL DEFAULT NULL,
  MODIFY `fecha_act` DATE NULL DEFAULT NULL,
  MODIFY `hora_act` TIME NULL DEFAULT NULL;
