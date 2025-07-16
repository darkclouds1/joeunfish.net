/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.9-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: joeu_fish
-- ------------------------------------------------------
-- Server version	10.11.9-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tdayprodmat_mst`
--

DROP TABLE IF EXISTS `tdayprodmat_mst`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tdayprodmat_mst` (
  `prod_cd` varchar(20) NOT NULL COMMENT '완제품코드',
  `mat_cd` varchar(20) NOT NULL COMMENT '재료코드',
  `mat_nm` varchar(100) NOT NULL COMMENT '재료명',
  `unit` varchar(20) NOT NULL COMMENT '단위',
  `order_num` smallint(6) NOT NULL COMMENT '표시순서',
  `inv_yn` varchar(1) DEFAULT NULL COMMENT '사용량이 아닌 재고로 관리',
  `use_yn` char(1) DEFAULT 'Y' COMMENT '사용여부',
  `create_dt` timestamp NULL DEFAULT current_timestamp() COMMENT '생성일시',
  `create_id` varchar(50) NOT NULL COMMENT '생성자',
  `modify_dt` timestamp NULL DEFAULT NULL COMMENT '변경일시',
  `modify_id` varchar(50) DEFAULT NULL COMMENT '변경자',
  PRIMARY KEY (`prod_cd`,`mat_cd`),
  CONSTRAINT `tdayprodmat_mst_ibfk_1` FOREIGN KEY (`prod_cd`) REFERENCES `tdayprod_mst` (`prod_cd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='완제품별재료마스터';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tdayprodmat_mst`
--

LOCK TABLES `tdayprodmat_mst` WRITE;
/*!40000 ALTER TABLE `tdayprodmat_mst` DISABLE KEYS */;
INSERT INTO `tdayprodmat_mst` VALUES
('P001','M001','명태','Box',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:47:25','proxima'),
('P002','M001','명태','Box',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:47:57','proxima'),
('P003','M002','고등어','Box',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:48:17','proxima'),
('P004','M002','고등어','Box',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:48:26','proxima'),
('P005','M003','문어','Box',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:48:44','proxima'),
('P006','M003','문어','Box',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:49:00','proxima'),
('P007','M003','문어','Box',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:49:11','proxima'),
('P008','M003','문어','Box',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:49:21','proxima'),
('P009','M004','절단낙지','kg',1,'N','Y','2024-11-15 04:25:11','system','2025-04-14 02:49:31','proxima'),
('P010','M005','데친문어','kg',1,NULL,'Y','2024-11-15 04:25:11','system','2024-11-25 08:52:31','root'),
('P010','M006','가리비','kg',2,'Y','N','2024-11-15 04:25:11','system','2025-05-09 04:12:54','proxima'),
('P010','M007','흰다리새우','kg',6,'N','N','2024-11-15 04:25:11','system','2025-04-14 02:49:46','proxima'),
('P010','M008','전복','kg',8,'Y','Y','2024-11-15 04:25:11','system','2024-11-25 08:54:26','joeunfish'),
('P010','M009','바지락','kg',5,'Y','Y','2024-11-15 04:25:11','system','2025-02-26 02:39:19','root'),
('P010','M010','꽃게','kg',4,NULL,'Y','2024-11-15 04:25:11','system','2024-11-25 08:53:40','joeunfish'),
('P010','M011','야채','EA',9,'Y','Y','2024-11-15 04:25:11','system','2025-02-26 02:39:34','root'),
('P010','M012','소스','EA',10,'Y','N','2024-11-15 04:25:11','system','2025-03-07 02:26:38','proxima'),
('P010','M028','손질갑오징어','kg',7,'N','Y','2025-03-06 01:04:23','root','2025-04-12 15:49:58','root'),
('P010','m035','피뿔고둥','kg',3,'Y','Y','2025-05-09 04:11:48','proxima',NULL,NULL),
('P011','M004','절단낙지','kg',5,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P011','M007','흰다리새우','kg',3,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P011','M011','야채','EA',6,'Y','Y','2024-11-15 04:25:11','system','2025-04-14 02:50:25','proxima'),
('P011','M012','소스','EA',7,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P011','M013','명태알','EA',1,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P011','M014','명태고니','kg',2,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P011','M015','생합','kg',4,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P011','M016','전분','EA',8,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P012','M011','야채','EA',2,'Y','Y','2024-11-15 04:25:11','system','2025-02-26 02:39:53','root'),
('P012','M012','소스','EA',4,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P012','M017','대구','kg',1,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P012','M018','고니','kg',5,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P012','M019','알','kg',6,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P013','M020','TRIM E','Box',1,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P014','M021','TRIM B','Box',1,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P015','M022','완출','kg',1,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P016','M022','완출','kg',1,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P017','M021','TRIM B','BOX',1,NULL,'Y','2025-02-06 10:00:58','root',NULL,NULL),
('P018','M024','TRIM E 냉동','kg',0,NULL,'Y','2024-11-15 04:25:11','system',NULL,NULL),
('P019','M022','완출','kg',1,NULL,'Y','2024-11-22 01:32:54','proxima',NULL,NULL),
('P020','M021','TRIM B','BOX',1,NULL,'Y','2024-11-22 01:35:54','proxima','2025-02-06 10:06:51','root'),
('P021','M027','연어알원료','kg',1,NULL,'Y','2024-11-25 04:45:11','joeunfish','2025-02-20 01:35:14','proxima'),
('P022','M025','블랙타이거새우','kg',1,NULL,'Y','2024-12-19 02:40:37','root','2024-12-19 02:40:49','root'),
('p023','M020','TRIM E','BOX',1,NULL,'Y','2025-02-19 06:11:00','proxima',NULL,NULL),
('p024','M026','생물백조기','BOX',1,NULL,'Y','2025-02-19 06:11:57','proxima',NULL,NULL),
('P025','M021','TRIM B','Box',1,'N','Y','2025-03-10 08:44:33','proxima','2025-03-10 08:50:56','proxima'),
('P026','M011','야채','EA',2,'Y','Y','2025-03-10 08:52:07','proxima','2025-03-10 08:52:20','proxima'),
('P026','M029','도다리','kg',1,'N','Y','2025-04-15 06:21:31','proxima',NULL,NULL),
('P026','M030','쑥',' EA',3,'Y','Y','2025-03-10 08:50:37','proxima',NULL,NULL),
('p027','M031','고등어필렛','BOX',1,'N','Y','2025-04-09 06:16:45','proxima','2025-04-16 07:12:18','proxima'),
('P028','M032','ASC전복18미','kg',1,'Y','Y','2025-04-09 06:17:49','proxima','2025-06-23 09:20:09','proxima'),
('P028','M037','전복살','kg',2,'N','Y','2025-06-23 09:13:29','proxima','2025-06-27 01:51:19','proxima'),
('p029','m011','야채','EA',2,'Y','Y','2025-04-30 04:40:23','proxima','2025-04-30 04:40:35','proxima'),
('p029','m012','소스','EA',3,'Y','Y','2025-04-30 04:42:16','proxima',NULL,NULL),
('p029','m016','전분',' EA',4,'Y','Y','2025-04-30 04:43:18','proxima',NULL,NULL),
('p029','m033','아귀','kg',1,'N','Y','2025-04-30 04:39:25','proxima','2025-04-30 04:41:12','proxima'),
('p029','m034','오만둥이','kg',5,'Y','Y','2025-04-30 04:44:34','proxima',NULL,NULL),
('p030','m011','야채',' EA',2,'Y','Y','2025-06-03 05:19:58','proxima',NULL,NULL),
('p030','m012','소스',' EA',3,'Y','Y','2025-06-03 05:20:39','proxima',NULL,NULL),
('p030','m036','민어','kg',1,'N','Y','2025-06-03 05:29:02','proxima','2025-06-30 02:20:34','proxima');
/*!40000 ALTER TABLE `tdayprodmat_mst` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-12 16:41:16
