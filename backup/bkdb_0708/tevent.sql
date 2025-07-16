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
-- Table structure for table `tevent`
--

DROP TABLE IF EXISTS `tevent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tevent` (
  `serial_no` int(11) NOT NULL AUTO_INCREMENT COMMENT '일련번호',
  `increate_dt` timestamp NULL DEFAULT NULL COMMENT '입력일자',
  `major_code` varchar(20) NOT NULL COMMENT '행사 대코드',
  `code` varchar(20) NOT NULL COMMENT '행사 코드',
  `event_name` varchar(200) NOT NULL COMMENT '행사명',
  `event_desc` text DEFAULT NULL COMMENT '행사 상세설명',
  `start_date` date NOT NULL COMMENT '시작일',
  `end_date` date NOT NULL COMMENT '종료일',
  `days` int(11) GENERATED ALWAYS AS (to_days(`end_date`) - to_days(`start_date`) + 1) STORED COMMENT '행사 기간(일)',
  `prod_code` varchar(20) DEFAULT NULL COMMENT '상품코드',
  `prod_name` varchar(100) DEFAULT NULL COMMENT '상품명',
  `unit_price` decimal(6,0) DEFAULT NULL COMMENT '단가',
  `target_qty` int(11) DEFAULT NULL COMMENT '목표 수량',
  `result_qty` int(11) DEFAULT NULL COMMENT '실적 수량',
  `use_yn` char(1) NOT NULL DEFAULT 'Y' COMMENT '사용여부(Y/N)',
  `create_dt` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  `create_id` varchar(20) DEFAULT NULL COMMENT '생성자ID',
  `modify_dt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '변경일시',
  `modify_id` varchar(20) DEFAULT NULL COMMENT '변경자ID',
  PRIMARY KEY (`serial_no`),
  KEY `idx_date` (`start_date`,`end_date`),
  KEY `idx_code` (`major_code`,`code`),
  KEY `idx_use_yn` (`use_yn`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='행사기획 관리 테이블';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tevent`
--

LOCK TABLES `tevent` WRITE;
/*!40000 ALTER TABLE `tevent` DISABLE KEYS */;
INSERT INTO `tevent` VALUES
(1,'2025-04-10 00:00:00','002','001','기타행사','코스트코 4월 연어행사 팩당5000원 사후정산','2025-04-07','2025-04-13',7,'2000000560770','연어필렛',5000,0,0,'Y','2025-04-10 07:34:48','root','2025-04-10 07:34:48','root');
/*!40000 ALTER TABLE `tevent` ENABLE KEYS */;
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
