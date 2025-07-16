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
-- Table structure for table `tdayprod_mst`
--

DROP TABLE IF EXISTS `tdayprod_mst`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tdayprod_mst` (
  `prod_cd` varchar(20) NOT NULL COMMENT '완제품코드',
  `prod_nm` varchar(100) NOT NULL COMMENT '완제품명',
  `unit` varchar(20) NOT NULL COMMENT '단위',
  `order_num` smallint(6) NOT NULL COMMENT '조회순서',
  `use_yn` char(1) DEFAULT 'Y' COMMENT '사용여부',
  `create_dt` timestamp NULL DEFAULT current_timestamp() COMMENT '생성일시',
  `create_id` varchar(50) NOT NULL COMMENT '생성자',
  `modify_dt` timestamp NULL DEFAULT NULL COMMENT '변경일시',
  `modify_id` varchar(50) DEFAULT NULL COMMENT '변경자',
  PRIMARY KEY (`prod_cd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='완제품마스터';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tdayprod_mst`
--

LOCK TABLES `tdayprod_mst` WRITE;
/*!40000 ALTER TABLE `tdayprod_mst` DISABLE KEYS */;
INSERT INTO `tdayprod_mst` VALUES
('P001','반건명태','2kg 봉',1,'Y','2024-11-15 04:20:44','system',NULL,NULL),
('P002','명태조림','EA',2,'Y','2024-11-15 04:20:44','system',NULL,NULL),
('P003','자반고등어','kg',3,'Y','2024-11-15 04:20:44','system',NULL,NULL),
('P004','고등어파지','kg',4,'Y','2024-11-15 04:20:44','system','2025-02-21 00:47:36','proxima'),
('P005','데친문어','Box',8,'Y','2024-11-15 04:20:44','system','2025-04-10 00:10:07','root'),
('P006','데친문어(해물탕)','Box',9,'Y','2024-11-15 04:20:44','system','2025-04-09 06:24:08','proxima'),
('P007','데친문어(호텔)','Box',10,'Y','2024-11-15 04:20:44','system','2025-02-20 06:14:48','root'),
('P008','데친문어 기타','Box',11,'Y','2024-11-15 04:20:44','system','2025-02-20 06:15:00','root'),
('P009','데친낙지','kg',12,'Y','2024-11-15 04:20:44','system',NULL,NULL),
('P010','문어해물탕','EA',14,'Y','2024-11-15 04:20:44','system','2025-04-10 00:10:48','root'),
('P011','해물알찜','EA',18,'n','2024-11-15 04:20:44','system','2024-11-19 08:08:52','ha1217'),
('P012','대구매운탕','EA',19,'n','2024-11-15 04:20:44','system','2025-01-15 02:21:03','proxima'),
('P013','연어필렛','Box',20,'Y','2024-11-15 04:20:44','system',NULL,NULL),
('P014','연어스테이크','Box',21,'Y','2024-11-15 04:20:44','system',NULL,NULL),
('P015','GS 200','EA',25,'Y','2024-11-15 04:20:44','system','2024-11-25 04:43:19','joeunfish'),
('P016','GS 250','EA',24,'Y','2024-11-15 04:20:44','system',NULL,NULL),
('P017','GS 300','EA',29,'Y','2024-11-15 04:20:44','system','2024-11-25 04:43:49','joeunfish'),
('P018','GS 400','EA',26,'n','2024-11-15 04:20:44','system','2024-11-28 04:35:54','proxima'),
('P019','GS 편의점 200',' EA',27,'Y','2024-11-22 01:31:48','proxima',NULL,NULL),
('P020','GS 편의점 300','EA',28,'N','2024-11-22 01:34:21','proxima','2025-01-17 01:27:33','proxima'),
('P021','연어알 200g','EA',23,'Y','2024-11-25 04:43:01','joeunfish','2024-11-28 04:34:51','proxima'),
('P022','블랙타이거새우','kg',7,'N','2024-12-19 02:39:48','root','2025-04-10 00:05:53','root'),
('p023','GS 연어필렛 1.2kg','EA',30,'Y','2025-01-23 02:24:28','proxima','2025-01-23 02:26:26','proxima'),
('p024','생물백조기','BOX',6,'Y','2025-02-19 05:11:01','proxima','2025-05-01 05:59:50','proxima'),
('P025','연어스테이크(벌크)','BOX',22,'Y','2025-03-10 08:43:14','proxima',NULL,NULL),
('P026','생물도다리쑥국','EA',17,'n','2025-03-10 08:47:09','proxima','2025-06-03 05:17:21','proxima'),
('p027','노르웨이고등어필렛 1.1kg','EA',5,'Y','2025-04-09 06:10:23','proxima','2025-04-09 06:20:37','proxima'),
('P028','전복슬라이스(550g)','EA',13,'Y','2025-04-09 06:13:16','proxima','2025-04-10 00:08:46','root'),
('p029','생물아귀찜','EA',16,'N','2025-04-30 04:36:40','proxima','2025-06-19 11:48:42','proxima'),
('p030','민어매운탕','EA',15,'y','2025-06-03 05:15:24','proxima','2025-06-16 02:26:50','proxima');
/*!40000 ALTER TABLE `tdayprod_mst` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-12 16:52:33
