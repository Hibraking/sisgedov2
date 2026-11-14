-- =====================================================================
--  SISGEDO — MIGRACIÓN 010: Siglas de las oficinas (numeración automática)
-- ---------------------------------------------------------------------
--  Formato del número: 001-2026-MPH/GM  (correlativo-año-siglas).
--  Generadas a partir del nombre de cada oficina. Se pueden corregir luego
--  en Mantenimiento > Oficinas. Solo llena las oficinas que aún NO tienen
--  siglas (no pisa las que ya se hayan escrito a mano). Es IDEMPOTENTE.
-- =====================================================================
SET NAMES utf8mb4;

UPDATE oficinas SET siglas = 'MPH/AT' WHERE idoficinas = 48 AND TRIM(IFNULL(siglas,'')) = '';  -- ANALISTA TECNICO
UPDATE oficinas SET siglas = 'MPH/AP' WHERE idoficinas = 52 AND TRIM(IFNULL(siglas,'')) = '';  -- Acceso Público
UPDATE oficinas SET siglas = 'MPH/AyF' WHERE idoficinas = 49 AND TRIM(IFNULL(siglas,'')) = '';  -- Administración y Finanzas
UPDATE oficinas SET siglas = 'MPH/ALM' WHERE idoficinas = 23 AND TRIM(IFNULL(siglas,'')) = '';  -- Almacen
UPDATE oficinas SET siglas = 'MPH/ATyA' WHERE idoficinas = 29 AND TRIM(IFNULL(siglas,'')) = '';  -- Asistencia Técnica y Administrativa
UPDATE oficinas SET siglas = 'MPH/CP' WHERE idoficinas = 44 AND TRIM(IFNULL(siglas,'')) = '';  -- Comisión de Procesos
UPDATE oficinas SET siglas = 'MPH/DEMUNA' WHERE idoficinas = 56 AND TRIM(IFNULL(siglas,'')) = '';  -- DEMUNA
UPDATE oficinas SET siglas = 'MPH/DPOyL' WHERE idoficinas = 58 AND TRIM(IFNULL(siglas,'')) = '';  -- DIVISION DE PROYECTOS, OBRAS Y LIQUIDACION
UPDATE oficinas SET siglas = 'MPH/DC' WHERE idoficinas = 24 AND TRIM(IFNULL(siglas,'')) = '';  -- Defensa Civil
UPDATE oficinas SET siglas = 'MPH/DAT' WHERE idoficinas = 25 AND TRIM(IFNULL(siglas,'')) = '';  -- División de Administración Tributaria
UPDATE oficinas SET siglas = 'MPH/GI' WHERE idoficinas = 47 AND TRIM(IFNULL(siglas,'')) = '';  -- GESTOR DE INVERSIONES
UPDATE oficinas SET siglas = 'MPH/GM' WHERE idoficinas = 5 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia Municipal
UPDATE oficinas SET siglas = 'MPH/GAJ' WHERE idoficinas = 26 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia de Asesoría Jurídica
UPDATE oficinas SET siglas = 'MPH/GDEyGA' WHERE idoficinas = 1 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia de Desarrollo Económico y Gestión Ambiental
UPDATE oficinas SET siglas = 'MPH/GDS' WHERE idoficinas = 27 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia de Desarrollo Social - (e) 
UPDATE oficinas SET siglas = 'MPH/GDTyI' WHERE idoficinas = 3 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia de Desarrollo Territorial e Infraestructura
UPDATE oficinas SET siglas = 'MPH/GPyP' WHERE idoficinas = 32 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia de Presupuesto y Planeamiento
UPDATE oficinas SET siglas = 'MPH/GSMyGA' WHERE idoficinas = 60 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia de Servicios Municipales y Gestión Ambiental
UPDATE oficinas SET siglas = 'MPH/GODUyR' WHERE idoficinas = 2 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia de obras Desarrollo Urbano y Rural
UPDATE oficinas SET siglas = 'MPH/GIVPH' WHERE idoficinas = 4 AND TRIM(IFNULL(siglas,'')) = '';  -- Gerencia del  IVP-H
UPDATE oficinas SET siglas = 'MPH/MP' WHERE idoficinas = 46 AND TRIM(IFNULL(siglas,'')) = '';  -- MONITOREO DE PROCESOS
UPDATE oficinas SET siglas = 'MPH/OPMI' WHERE idoficinas = 6 AND TRIM(IFNULL(siglas,'')) = '';  -- Oficina de Programación Multianual e Inversiones - OPMI
UPDATE oficinas SET siglas = 'MPH/PT' WHERE idoficinas = 36 AND TRIM(IFNULL(siglas,'')) = '';  -- Portal de Transparencia
UPDATE oficinas SET siglas = 'MPH/RUV' WHERE idoficinas = 41 AND TRIM(IFNULL(siglas,'')) = '';  -- Registro Único de Víctimas
UPDATE oficinas SET siglas = 'MPH/RATMS' WHERE idoficinas = 8 AND TRIM(IFNULL(siglas,'')) = '';  -- Responsable de ATMS
UPDATE oficinas SET siglas = 'MPH/RPTE' WHERE idoficinas = 51 AND TRIM(IFNULL(siglas,'')) = '';  -- Responsable del Portal de Transparencia Estándar
UPDATE oficinas SET siglas = 'MPH/SGSyL' WHERE idoficinas = 45 AND TRIM(IFNULL(siglas,'')) = '';  -- SUB GERENCIA DE SUPERVISIÓN Y LIQUIDACIÓN
UPDATE oficinas SET siglas = 'MPH/SR' WHERE idoficinas = 39 AND TRIM(IFNULL(siglas,'')) = '';  -- Sala de Regidores
UPDATE oficinas SET siglas = 'MPH/SAMP' WHERE idoficinas = 9 AND TRIM(IFNULL(siglas,'')) = '';  -- Secretaria de Alcaldía - Mesa de Partes
UPDATE oficinas SET siglas = 'MPH/SGJ' WHERE idoficinas = 54 AND TRIM(IFNULL(siglas,'')) = '';  -- Secretaría General - Jefatura
UPDATE oficinas SET siglas = 'MPH/ST' WHERE idoficinas = 55 AND TRIM(IFNULL(siglas,'')) = '';  -- Secretaría Técnica
UPDATE oficinas SET siglas = 'MPH/SC' WHERE idoficinas = 40 AND TRIM(IFNULL(siglas,'')) = '';  -- Seguridad Ciudadana
UPDATE oficinas SET siglas = 'MPH/SESC' WHERE idoficinas = 42 AND TRIM(IFNULL(siglas,'')) = '';  -- Sesión de Consejo
UPDATE oficinas SET siglas = 'MPH/SGC' WHERE idoficinas = 11 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Contabilidad 
UPDATE oficinas SET siglas = 'MPH/SGGA' WHERE idoficinas = 12 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Gestión  Ambiental
UPDATE oficinas SET siglas = 'MPH/SGGRH' WHERE idoficinas = 13 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Gestión de Recursos Humanos 
UPDATE oficinas SET siglas = 'MPH/SGL' WHERE idoficinas = 14 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Logistica
UPDATE oficinas SET siglas = 'MPH/SGPyP' WHERE idoficinas = 50 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Planeación y Presupuesto
UPDATE oficinas SET siglas = 'MPH/SGPSyDD' WHERE idoficinas = 15 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Prevención Social y Defensa de los Derechos (DEMUNA, OMAPED, CIAM )
UPDATE oficinas SET siglas = 'MPH/SGSyBS' WHERE idoficinas = 16 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Salud y Bienestar Social
UPDATE oficinas SET siglas = 'MPH/SGTCSVyM' WHERE idoficinas = 17 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Transporte, Circulación, Seguridad Vial y Maquinaria
UPDATE oficinas SET siglas = 'MPH/SGTTySV' WHERE idoficinas = 59 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Transporte, Transito y Seguridad Vial 
UPDATE oficinas SET siglas = 'MPH/SGTyA' WHERE idoficinas = 18 AND TRIM(IFNULL(siglas,'')) = '';  -- Sub Gerencia de Turismo y Artesania
UPDATE oficinas SET siglas = 'MPH/SGDTyI' WHERE idoficinas = 10 AND TRIM(IFNULL(siglas,'')) = '';  -- Subgerencia de desarrollo territorial e infraestructura
UPDATE oficinas SET siglas = 'MPH/ULE' WHERE idoficinas = 57 AND TRIM(IFNULL(siglas,'')) = '';  -- ULE - Padrón Nominal
UPDATE oficinas SET siglas = 'MPH/UF' WHERE idoficinas = 21 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad Formuladora
UPDATE oficinas SET siglas = 'MPH/UC' WHERE idoficinas = 43 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad de Conductores
UPDATE oficinas SET siglas = 'MPH/UCPyR' WHERE idoficinas = 19 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad de Control Previo y Recaudación
UPDATE oficinas SET siglas = 'MPH/UGM' WHERE idoficinas = 53 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad de Gestión Municipal
UPDATE oficinas SET siglas = 'MPH/UIIyRP' WHERE idoficinas = 7 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad de Imgen Institucional y Relaciones Públicas
UPDATE oficinas SET siglas = 'MPH/UP' WHERE idoficinas = 30 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad de Patrimonio
UPDATE oficinas SET siglas = 'MPH/URCyAD' WHERE idoficinas = 38 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad de Registro Civil y Archivo Documentario
UPDATE oficinas SET siglas = 'MPH/USM' WHERE idoficinas = 33 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad de Servicios Municipales
UPDATE oficinas SET siglas = 'MPH/UT' WHERE idoficinas = 20 AND TRIM(IFNULL(siglas,'')) = '';  -- Unidad de Tesorería
UPDATE oficinas SET siglas = 'MPH/ATM' WHERE idoficinas = 34 AND TRIM(IFNULL(siglas,'')) = '';  -- Área Técnica Municipal
UPDATE oficinas SET siglas = 'MPH/AA' WHERE idoficinas = 31 AND TRIM(IFNULL(siglas,'')) = '';  -- Área de Adquisiciones

SELECT COUNT(*) AS oficinas, SUM(TRIM(IFNULL(siglas,''))<>'') AS con_siglas FROM oficinas WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-');
