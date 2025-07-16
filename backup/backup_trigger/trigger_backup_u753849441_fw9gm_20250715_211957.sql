/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.9-MariaDB, for Linux (x86_64)
--
-- Host: 45.130.228.170    Database: u753849441_fw9gm
-- ------------------------------------------------------
-- Server version	10.11.10-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u753849441_cASrk`@`127.0.0.1`*/ /*!50003 TRIGGER update_lot_nm
BEFORE INSERT ON tpurchase
FOR EACH ROW
BEGIN
    SET NEW.lot_nm = CONCAT(DATE_FORMAT(NEW.input_dt, '%Y%m%d'),
                            '|',
                            NEW.vendor_nm,
                            '|',
                            IFNULL(CAST(NEW.box AS CHAR), 'N/A'));
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u753849441_cASrk`@`127.0.0.1`*/ /*!50003 TRIGGER trg_tpurchase_in_before_insert
BEFORE INSERT ON tpurchase_in
FOR EACH ROW
BEGIN
    DECLARE current_qty_cur DECIMAL(10,2);

    SELECT qty_cur
      INTO current_qty_cur
      FROM tpurchase
     WHERE serial_no = NEW.tpurchase_serial_no
     LIMIT 1;

    IF NEW.qty_in > current_qty_cur THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'ERROR: qty_in 값이 현재고(qty_cur)보다 클 수 없습니다.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u753849441_cASrk`@`127.0.0.1`*/ /*!50003 TRIGGER trg_tpurchase_in_bi
BEFORE INSERT ON tpurchase_in
FOR EACH ROW
BEGIN
    DECLARE v_vendor_nm VARCHAR(50);
    DECLARE v_mat_cd   VARCHAR(50);
    DECLARE v_mat_nm   VARCHAR(50);
    DECLARE v_in_date  VARCHAR(10);
    DECLARE v_qty_in   VARCHAR(10);
    DECLARE v_qty_incur VARCHAR(10);

    SELECT vendor_nm, mat_cd
      INTO v_vendor_nm, v_mat_cd
      FROM tpurchase
     WHERE serial_no = NEW.tpurchase_serial_no;

    SELECT mat_nm
      INTO v_mat_nm
      FROM tdayprodmat_mst
     WHERE mat_cd = v_mat_cd
     LIMIT 1;

    SET v_in_date   = DATE_FORMAT(NEW.increate_dt, '%m/%d');
    SET v_qty_in    = IF(NEW.qty_in    = FLOOR(NEW.qty_in),    CAST(NEW.qty_in    AS UNSIGNED), NEW.qty_in);
    SET v_qty_incur = IF(NEW.qty_incur = FLOOR(NEW.qty_incur), CAST(NEW.qty_incur AS UNSIGNED), NEW.qty_incur);

    SET NEW.lotin_nm = CONCAT(v_in_date, '-', v_vendor_nm, '-', v_mat_nm,
                              '-', v_qty_in, '-', v_qty_incur);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u753849441_cASrk`@`127.0.0.1`*/ /*!50003 TRIGGER trg_tpurchase_in_after_insert
AFTER INSERT ON tpurchase_in
FOR EACH ROW
BEGIN
    UPDATE tpurchase
    SET qty_cur = qty_cur - NEW.qty_in
    WHERE serial_no = NEW.tpurchase_serial_no;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u753849441_cASrk`@`127.0.0.1`*/ /*!50003 TRIGGER trg_tpurchase_in_before_update
BEFORE UPDATE ON tpurchase_in
FOR EACH ROW
BEGIN
    DECLARE current_qty_cur DECIMAL(10,2);

    SELECT qty_cur
      INTO current_qty_cur
      FROM tpurchase
     WHERE serial_no = NEW.tpurchase_serial_no
     LIMIT 1;

    IF (NEW.qty_in - OLD.qty_in) > current_qty_cur THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'ERROR: 수정된 qty_in 값이 현재고(qty_cur)보다 클 수 없습니다.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u753849441_cASrk`@`127.0.0.1`*/ /*!50003 TRIGGER update_lotin_nm
BEFORE UPDATE ON tpurchase_in
FOR EACH ROW
BEGIN
    DECLARE v_vendor_nm VARCHAR(50);
    DECLARE v_mat_cd   VARCHAR(50);
    DECLARE v_mat_nm   VARCHAR(50);
    DECLARE v_in_date  VARCHAR(10);
    DECLARE v_qty_in   VARCHAR(10);
    DECLARE v_qty_incur VARCHAR(10);

    SELECT vendor_nm, mat_cd
      INTO v_vendor_nm, v_mat_cd
      FROM tpurchase
     WHERE serial_no = NEW.tpurchase_serial_no;

    SELECT mat_nm
      INTO v_mat_nm
      FROM tdayprodmat_mst
     WHERE mat_cd = v_mat_cd
     LIMIT 1;

    SET v_in_date   = DATE_FORMAT(NEW.increate_dt, '%m/%d');
    SET v_qty_in    = IF(NEW.qty_in    = FLOOR(NEW.qty_in),    CAST(NEW.qty_in    AS UNSIGNED), NEW.qty_in);
    SET v_qty_incur = IF(NEW.qty_incur = FLOOR(NEW.qty_incur), CAST(NEW.qty_incur AS UNSIGNED), NEW.qty_incur);

    IF OLD.increate_dt <> NEW.increate_dt
       OR OLD.qty_incur  <> NEW.qty_incur THEN
        SET NEW.lotin_nm = CONCAT(v_in_date, '-', v_vendor_nm, '-', v_mat_nm,
                                  '-', v_qty_in, '-', v_qty_incur);
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u753849441_cASrk`@`127.0.0.1`*/ /*!50003 TRIGGER trg_tpurchase_in_after_update
AFTER UPDATE ON tpurchase_in
FOR EACH ROW
BEGIN
    UPDATE tpurchase
    SET qty_cur = qty_cur + OLD.qty_in - NEW.qty_in
    WHERE serial_no = NEW.tpurchase_serial_no;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`u753849441_cASrk`@`127.0.0.1`*/ /*!50003 TRIGGER trg_tpurchase_in_after_delete
AFTER DELETE ON tpurchase_in
FOR EACH ROW
BEGIN
    UPDATE tpurchase
    SET qty_cur = qty_cur + OLD.qty_in
    WHERE serial_no = OLD.tpurchase_serial_no;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-15 21:22:03
