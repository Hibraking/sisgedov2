-- =====================================================================
--  SISGEDO — MIGRACIÓN 004: Recuperar responsables borrados
-- ---------------------------------------------------------------------
--  QUÉ HACE:
--   Los responsables 19, 21, 22, 23, 30, 34, 36, 44, 45, 50, 56 y 63 quedaron sin persona ni
--   oficina (idpersonal/idoficina = NULL), por eso el historial y los
--   reportes mostraban nombres y oficinas en blanco.
--   - La oficina de cada uno se dedujo de sus movimientos.
--   - La persona, de las iniciales con que firmaban sus documentos.
--   - Los que no se pudieron identificar quedan con un nombre provisional
--     ("Responsable anterior") que se puede corregir luego en Personal.
--   - Los responsables se dejan INACTIVOS (estado 0): son históricos y
--     no deben aparecer para recibir documentos nuevos.
--   - Renombra la oficina 3 ("-").
--
--  CÓMO EJECUTAR:
--   - phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--   - Local: mysql -u root sisgedov2 < migraciones/004_recuperar_responsables.sql
--  Es IDEMPOTENTE: solo toca responsables que siguen vacíos y no duplica
--  los nombres provisionales si se ejecuta otra vez.
-- =====================================================================
SET NAMES utf8mb4;
START TRANSACTION;

-- 1) Oficina 3: antes "-"
UPDATE oficinas SET nombre = 'Gerencia de Desarrollo Territorial e Infraestructura'
 WHERE idoficinas = 3 AND (nombre IS NULL OR TRIM(nombre) IN ('', '-'));

-- 2) Identificados por sus iniciales de firma (personas ya registradas)
--    #19 Unidad Formuladora           -> KNRC  Katerine Nyccol RAFAEL CHUCOS (personal 9)
--    #34 Control Previo y Recaudación -> HHR   Hugo HUAROTO RIOS (personal 17)
--    #21 Subg. Desarrollo Territorial -> MAHD  Miguel Angel Huaman Donayre (personal 32)
UPDATE responsables SET idpersonal = 9,  idoficina = 21 WHERE idresponsables = 19 AND idpersonal IS NULL;
UPDATE responsables SET idpersonal = 17, idoficina = 19 WHERE idresponsables = 34 AND idpersonal IS NULL;
UPDATE responsables SET idpersonal = 32, idoficina = 10 WHERE idresponsables = 21 AND idpersonal IS NULL;

-- 3) No identificados: persona provisional (sin usuario ni clave: no puede entrar)
INSERT INTO personal (nombres, apellidos)
SELECT 'Responsable anterior', '(firma JSVT)' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(firma JSVT)');
INSERT INTO personal (nombres, apellidos)
SELECT 'Responsable anterior', '(firma NFAM)' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(firma NFAM)');
INSERT INTO personal (nombres, apellidos)
SELECT 'Responsable anterior', '(Gerencia Municipal)' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(Gerencia Municipal)');

--    #44 Gerencia de Desarrollo Territorial e Infraestructura (oficina 3)
UPDATE responsables SET idoficina = 3,
       idpersonal = (SELECT idpersonal FROM (SELECT idpersonal FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(firma JSVT)' LIMIT 1) t)
 WHERE idresponsables = 44 AND idpersonal IS NULL;
--    #36 Sub Gerencia de Salud y Bienestar Social (oficina 16)
UPDATE responsables SET idoficina = 16,
       idpersonal = (SELECT idpersonal FROM (SELECT idpersonal FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(firma NFAM)' LIMIT 1) t)
 WHERE idresponsables = 36 AND idpersonal IS NULL;
--    #50 Gerencia Municipal (oficina 5)
UPDATE responsables SET idoficina = 5,
       idpersonal = (SELECT idpersonal FROM (SELECT idpersonal FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(Gerencia Municipal)' LIMIT 1) t)
 WHERE idresponsables = 50 AND idpersonal IS NULL;

-- 4) Otros responsables vacíos con pocos documentos (2019-2021)
--    #56 Sala de Regidores -> firma JAECH = José Antonio ESCOBAR CHUQUIHUACCHA (personal 50)
UPDATE responsables SET idpersonal = 50, idoficina = 39 WHERE idresponsables = 56 AND idpersonal IS NULL;

--    Sin iniciales de firma: persona provisional común, oficina deducida
INSERT INTO personal (nombres, apellidos)
SELECT 'Responsable anterior', '(no identificado)' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(no identificado)');
SET @ni = (SELECT idpersonal FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(no identificado)' LIMIT 1);
UPDATE responsables SET idpersonal = @ni, idoficina = 24 WHERE idresponsables = 22 AND idpersonal IS NULL; -- Defensa Civil
UPDATE responsables SET idpersonal = @ni, idoficina = 18 WHERE idresponsables = 23 AND idpersonal IS NULL; -- Turismo y Artesanía
UPDATE responsables SET idpersonal = @ni, idoficina = 31 WHERE idresponsables = 30 AND idpersonal IS NULL; -- Área de Adquisiciones
UPDATE responsables SET idpersonal = @ni, idoficina = 36 WHERE idresponsables = 45 AND idpersonal IS NULL; -- Portal de Transparencia
UPDATE responsables SET idpersonal = @ni, idoficina = 41 WHERE idresponsables = 63 AND idpersonal IS NULL; -- Registro Único de Víctimas

COMMIT;

-- Verificación: deben salir las 12 filas con persona y oficina
SELECT r.idresponsables, r.estado, o.nombre AS oficina, CONCAT_WS(' ', p.nombres, p.apellidos) AS persona
  FROM responsables r
  LEFT JOIN oficinas o ON o.idoficinas = r.idoficina
  LEFT JOIN personal p ON p.idpersonal = r.idpersonal
 WHERE r.idresponsables IN (19, 21, 22, 23, 30, 34, 36, 44, 45, 50, 56, 63);
