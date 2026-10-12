-- Migración: Agregar campos de geolocalización, dirección, horarios y WhatsApp a cc_sucursales
ALTER TABLE cc_sucursales ADD COLUMN IF NOT EXISTS direccion TEXT NULL;
ALTER TABLE cc_sucursales ADD COLUMN IF NOT EXISTS latitud VARCHAR(50) NULL;
ALTER TABLE cc_sucursales ADD COLUMN IF NOT EXISTS longitud VARCHAR(50) NULL;
ALTER TABLE cc_sucursales ADD COLUMN IF NOT EXISTS horario VARCHAR(150) NULL;
ALTER TABLE cc_sucursales ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(50) NULL;
