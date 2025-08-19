-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 19. Aug, 2025 20:22 PM
-- Tjener-versjon: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `skipsdb`
--

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblfartnavn`
--

CREATE TABLE `tblfartnavn` (
  `FartNavn_ID` int(11) NOT NULL,
  `FartObj_ID` int(11) NOT NULL DEFAULT 1,
  `FartNavn` varchar(100) DEFAULT NULL,
  `FartType_ID` int(11) DEFAULT NULL,
  `PennantTiln` varchar(50) DEFAULT NULL,
  `TidlNavn` varchar(255) DEFAULT NULL,
  `FartNotater` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblfartobj`
--

CREATE TABLE `tblfartobj` (
  `FartObj_ID` int(11) NOT NULL,
  `NavnObj` varchar(100) DEFAULT NULL,
  `FartType_ID` int(11) DEFAULT 1,
  `IMO` int(11) DEFAULT NULL,
  `Kontrahert` varchar(255) DEFAULT NULL,
  `Kjolstrukket` varchar(255) DEFAULT NULL,
  `Sjosatt` varchar(255) DEFAULT NULL,
  `Levert` varchar(255) DEFAULT NULL,
  `Bygget` varchar(255) DEFAULT NULL,
  `LeverID` int(11) DEFAULT 1,
  `SkrogID` int(11) DEFAULT 1,
  `BnrSkrog` varchar(255) DEFAULT NULL,
  `StroketYear` smallint(6) DEFAULT NULL,
  `StroketID` int(11) DEFAULT NULL,
  `Historikk` text DEFAULT NULL,
  `ObjNotater` mediumtext DEFAULT NULL,
  `IngenData` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblfartspes`
--

CREATE TABLE `tblfartspes` (
  `FartSpes_ID` int(11) NOT NULL,
  `FartObj_ID` int(11) NOT NULL DEFAULT 1,
  `YearSpes` smallint(6) DEFAULT NULL,
  `MndSpes` tinyint(4) DEFAULT NULL,
  `Verft_ID` int(11) DEFAULT NULL,
  `Byggenr` varchar(255) DEFAULT NULL,
  `SkrogID` int(11) DEFAULT NULL,
  `BnrSkrog` varchar(255) DEFAULT NULL,
  `Materiale` varchar(255) DEFAULT NULL,
  `FartMat_ID` int(11) DEFAULT NULL,
  `FartType_ID` int(11) DEFAULT NULL,
  `FartFunk_ID` int(11) DEFAULT NULL,
  `FartSkrog_ID` int(11) DEFAULT NULL,
  `FartDrift_ID` int(11) DEFAULT NULL,
  `FunkDetalj` varchar(255) DEFAULT NULL,
  `TeknDetalj` varchar(255) DEFAULT NULL,
  `FartKlasse_ID` int(11) DEFAULT 1,
  `Fartklasse` varchar(255) DEFAULT NULL,
  `Kapasitet` varchar(255) DEFAULT NULL,
  `Rigg` varchar(50) DEFAULT NULL,
  `FartRigg_ID` int(11) DEFAULT 1,
  `FartMotor_ID` int(11) DEFAULT 1,
  `MotorDetalj` varchar(50) DEFAULT NULL,
  `MotorEff` varchar(20) DEFAULT NULL,
  `MaxFart` smallint(6) DEFAULT NULL,
  `Lengde` smallint(6) DEFAULT NULL,
  `Bredde` smallint(6) DEFAULT NULL,
  `Dypg` smallint(6) DEFAULT NULL,
  `Tonnasje` varchar(255) DEFAULT NULL,
  `Drektigh` varchar(255) DEFAULT NULL,
  `TonnEnh_ID` int(11) DEFAULT NULL,
  `Objekt` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblfarttid`
--

CREATE TABLE `tblfarttid` (
  `FartTid_ID` int(11) NOT NULL,
  `YearTid` smallint(6) DEFAULT NULL,
  `MndTid` tinyint(4) DEFAULT NULL,
  `FartObj_ID` int(11) DEFAULT NULL,
  `FartNavn_ID` int(11) DEFAULT NULL,
  `FartSpes_ID` int(11) DEFAULT NULL,
  `Objekt` tinyint(1) DEFAULT NULL,
  `Rederi` varchar(255) DEFAULT NULL,
  `Nasjon_ID` int(11) DEFAULT NULL,
  `RegHavn` varchar(50) DEFAULT NULL,
  `MMSI` varchar(15) DEFAULT NULL,
  `Kallesignal` varchar(15) DEFAULT NULL,
  `Fiskerinr` varchar(255) DEFAULT NULL,
  `Navning` tinyint(1) DEFAULT NULL,
  `Eierskifte` tinyint(1) DEFAULT NULL,
  `Annet` tinyint(1) DEFAULT NULL,
  `Hendelse` varchar(255) DEFAULT NULL,
  `Historie` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblverft`
--

CREATE TABLE `tblverft` (
  `Verft_ID` int(11) NOT NULL,
  `VerftNavn` varchar(255) DEFAULT NULL,
  `Sted` varchar(255) DEFAULT NULL,
  `Nasjon_ID` int(11) DEFAULT NULL,
  `TidlID` int(11) DEFAULT NULL,
  `Etablert` smallint(6) DEFAULT NULL,
  `Nedlagt` smallint(6) DEFAULT NULL,
  `EtterID` int(11) DEFAULT NULL,
  `Merknad` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblxdigmuseum`
--

CREATE TABLE `tblxdigmuseum` (
  `ID` int(11) NOT NULL,
  `FartNavn_ID` int(11) NOT NULL,
  `DIMUkode` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
  `Motiv` varchar(255) DEFAULT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblxfartlink`
--

CREATE TABLE `tblxfartlink` (
  `FartLk_ID` int(11) NOT NULL,
  `FartNavn_ID` int(11) NOT NULL DEFAULT 1,
  `LinkType_ID` int(11) DEFAULT NULL,
  `LinkType` varchar(50) DEFAULT NULL,
  `LinkInnh` varchar(50) DEFAULT NULL,
  `Link` mediumtext DEFAULT NULL,
  `SerNo` smallint(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblxnmmfoto`
--

CREATE TABLE `tblxnmmfoto` (
  `ID` int(11) NOT NULL,
  `FartNavn_ID` int(11) DEFAULT NULL,
  `Bilde_Fil` varchar(50) DEFAULT NULL,
  `URL_Bane` varchar(255) NOT NULL DEFAULT '/assets/img/skip/',
  `PrimusNavn` varchar(255) DEFAULT NULL,
  `Motiv` varchar(255) DEFAULT NULL,
  `Fotograf` varchar(255) DEFAULT NULL,
  `FotoFirma` varchar(255) DEFAULT NULL,
  `Samling` varchar(255) DEFAULT NULL,
  `FotoTid` varchar(255) DEFAULT NULL,
  `FotoSted` varchar(255) DEFAULT NULL,
  `SvartFarge` varchar(255) DEFAULT NULL,
  `Referanse` varchar(255) DEFAULT NULL,
  `TekstFoto` varchar(255) DEFAULT NULL,
  `FriKopi` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblxverftlink`
--

CREATE TABLE `tblxverftlink` (
  `VerftLk_ID` int(11) NOT NULL,
  `Verft_ID` int(11) NOT NULL DEFAULT 1,
  `LinkType_ID` int(11) DEFAULT NULL,
  `LinkType` varchar(50) DEFAULT NULL,
  `LinkInnh` varchar(50) DEFAULT NULL,
  `Link` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartdrift`
--

CREATE TABLE `tblzfartdrift` (
  `FartDrift_ID` int(11) NOT NULL,
  `DriftMiddel` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartfunk`
--

CREATE TABLE `tblzfartfunk` (
  `FartFunk_ID` int(11) NOT NULL,
  `TypeFunksjon` varchar(255) DEFAULT NULL,
  `FunkDet` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartklasse`
--

CREATE TABLE `tblzfartklasse` (
  `FartKlasse_ID` int(11) NOT NULL,
  `KlasseNavn` varchar(255) DEFAULT NULL,
  `TypeKlasse` varchar(255) DEFAULT NULL,
  `Klasse` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartmat`
--

CREATE TABLE `tblzfartmat` (
  `FartMat_ID` int(11) NOT NULL,
  `MatFork` varchar(50) DEFAULT NULL,
  `Materiale` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartmotor`
--

CREATE TABLE `tblzfartmotor` (
  `FartMotor_ID` int(11) NOT NULL,
  `MotorFork` varchar(10) DEFAULT NULL,
  `MotorDetalj` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartrigg`
--

CREATE TABLE `tblzfartrigg` (
  `FartRigg_ID` int(11) NOT NULL,
  `RiggFork` varchar(3) DEFAULT NULL,
  `RiggDetalj` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartskrog`
--

CREATE TABLE `tblzfartskrog` (
  `FartSkrog_ID` int(11) NOT NULL,
  `TypeSkrog` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfarttype`
--

CREATE TABLE `tblzfarttype` (
  `FartType_ID` int(11) NOT NULL,
  `TypeFork` varchar(3) DEFAULT NULL,
  `FartType` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzlinktype`
--

CREATE TABLE `tblzlinktype` (
  `LinkType_ID` int(11) NOT NULL,
  `LinkType` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblznasjon`
--

CREATE TABLE `tblznasjon` (
  `Nasjon_ID` int(11) NOT NULL,
  `Nasjon` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzstroket`
--

CREATE TABLE `tblzstroket` (
  `Stroket_ID` int(11) NOT NULL,
  `Strok` varchar(255) DEFAULT NULL,
  `StrokDetalj` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblztonnenh`
--

CREATE TABLE `tblztonnenh` (
  `TonnEnh_ID` int(11) NOT NULL,
  `TonnFork` varchar(5) DEFAULT NULL,
  `TonnDetalj` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzuser`
--

CREATE TABLE `tblzuser` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `LastUsed` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblfartnavn`
--
ALTER TABLE `tblfartnavn`
  ADD KEY `FartNavn_ID` (`FartNavn_ID`),
  ADD KEY `FartObj_ID` (`FartObj_ID`),
  ADD KEY `FartTypeNavn` (`FartType_ID`),
  ADD KEY `ix_FartNavn_FartNavn` (`FartNavn`),
  ADD KEY `FartoyNavnObjektID` (`FartNavn`,`FartObj_ID`);

--
-- Indexes for table `tblfartobj`
--
ALTER TABLE `tblfartobj`
  ADD PRIMARY KEY (`FartObj_ID`),
  ADD KEY `LeverID` (`LeverID`),
  ADD KEY `SkrogID` (`SkrogID`),
  ADD KEY `FartTypeObj` (`FartType_ID`);

--
-- Indexes for table `tblfartspes`
--
ALTER TABLE `tblfartspes`
  ADD PRIMARY KEY (`FartSpes_ID`),
  ADD KEY `FartObj_ID` (`FartObj_ID`),
  ADD KEY `Verft_ID` (`Verft_ID`),
  ADD KEY `SkrogID` (`SkrogID`),
  ADD KEY `FartTypeSpes` (`FartType_ID`),
  ADD KEY `VerftObj` (`Verft_ID`,`FartObj_ID`);

--
-- Indexes for table `tblfarttid`
--
ALTER TABLE `tblfarttid`
  ADD PRIMARY KEY (`FartTid_ID`),
  ADD KEY `FartObj_ID` (`FartObj_ID`),
  ADD KEY `FartNavn_ID` (`FartNavn_ID`),
  ADD KEY `FartSpes_ID` (`FartSpes_ID`),
  ADD KEY `Nasjon_ID` (`Nasjon_ID`),
  ADD KEY `ix_FartTid_Navn_Tid` (`FartNavn_ID`,`YearTid`,`MndTid`,`FartTid_ID`),
  ADD KEY `ix_FartTid_Navn_Obj` (`FartNavn_ID`,`Objekt`,`FartObj_ID`),
  ADD KEY `ix_FartTid_Nasjon` (`Nasjon_ID`),
  ADD KEY `RederiObj` (`Rederi`,`FartObj_ID`),
  ADD KEY `ObjTidID` (`FartObj_ID`,`FartTid_ID`);

--
-- Indexes for table `tblverft`
--
ALTER TABLE `tblverft`
  ADD PRIMARY KEY (`Verft_ID`) USING BTREE,
  ADD KEY `Nasjon_ID` (`Nasjon_ID`),
  ADD KEY `VerftSted` (`VerftNavn`,`Sted`);

--
-- Indexes for table `tblxdigmuseum`
--
ALTER TABLE `tblxdigmuseum`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `FartNavnDIMU` (`FartNavn_ID`);

--
-- Indexes for table `tblxfartlink`
--
ALTER TABLE `tblxfartlink`
  ADD PRIMARY KEY (`FartLk_ID`) USING BTREE,
  ADD KEY `FartID` (`FartNavn_ID`);

--
-- Indexes for table `tblxnmmfoto`
--
ALTER TABLE `tblxnmmfoto`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `tblxverftlink`
--
ALTER TABLE `tblxverftlink`
  ADD PRIMARY KEY (`VerftLk_ID`),
  ADD KEY `Verft_ID` (`Verft_ID`);

--
-- Indexes for table `tblzfartdrift`
--
ALTER TABLE `tblzfartdrift`
  ADD PRIMARY KEY (`FartDrift_ID`);

--
-- Indexes for table `tblzfartfunk`
--
ALTER TABLE `tblzfartfunk`
  ADD PRIMARY KEY (`FartFunk_ID`);

--
-- Indexes for table `tblzfartklasse`
--
ALTER TABLE `tblzfartklasse`
  ADD PRIMARY KEY (`FartKlasse_ID`);

--
-- Indexes for table `tblzfartmat`
--
ALTER TABLE `tblzfartmat`
  ADD PRIMARY KEY (`FartMat_ID`);

--
-- Indexes for table `tblzfartmotor`
--
ALTER TABLE `tblzfartmotor`
  ADD PRIMARY KEY (`FartMotor_ID`);

--
-- Indexes for table `tblzfartrigg`
--
ALTER TABLE `tblzfartrigg`
  ADD PRIMARY KEY (`FartRigg_ID`);

--
-- Indexes for table `tblzfartskrog`
--
ALTER TABLE `tblzfartskrog`
  ADD PRIMARY KEY (`FartSkrog_ID`);

--
-- Indexes for table `tblzfarttype`
--
ALTER TABLE `tblzfarttype`
  ADD PRIMARY KEY (`FartType_ID`);

--
-- Indexes for table `tblzlinktype`
--
ALTER TABLE `tblzlinktype`
  ADD PRIMARY KEY (`LinkType_ID`);

--
-- Indexes for table `tblznasjon`
--
ALTER TABLE `tblznasjon`
  ADD PRIMARY KEY (`Nasjon_ID`);

--
-- Indexes for table `tblzstroket`
--
ALTER TABLE `tblzstroket`
  ADD PRIMARY KEY (`Stroket_ID`);

--
-- Indexes for table `tblztonnenh`
--
ALTER TABLE `tblztonnenh`
  ADD PRIMARY KEY (`TonnEnh_ID`),
  ADD UNIQUE KEY `TonnFork` (`TonnFork`);

--
-- Indexes for table `tblzuser`
--
ALTER TABLE `tblzuser`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblfartnavn`
--
ALTER TABLE `tblfartnavn`
  MODIFY `FartNavn_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblfartobj`
--
ALTER TABLE `tblfartobj`
  MODIFY `FartObj_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblfartspes`
--
ALTER TABLE `tblfartspes`
  MODIFY `FartSpes_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblfarttid`
--
ALTER TABLE `tblfarttid`
  MODIFY `FartTid_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblverft`
--
ALTER TABLE `tblverft`
  MODIFY `Verft_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblxdigmuseum`
--
ALTER TABLE `tblxdigmuseum`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblxfartlink`
--
ALTER TABLE `tblxfartlink`
  MODIFY `FartLk_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblxnmmfoto`
--
ALTER TABLE `tblxnmmfoto`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblxverftlink`
--
ALTER TABLE `tblxverftlink`
  MODIFY `VerftLk_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartdrift`
--
ALTER TABLE `tblzfartdrift`
  MODIFY `FartDrift_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartfunk`
--
ALTER TABLE `tblzfartfunk`
  MODIFY `FartFunk_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartklasse`
--
ALTER TABLE `tblzfartklasse`
  MODIFY `FartKlasse_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartmat`
--
ALTER TABLE `tblzfartmat`
  MODIFY `FartMat_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartmotor`
--
ALTER TABLE `tblzfartmotor`
  MODIFY `FartMotor_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartrigg`
--
ALTER TABLE `tblzfartrigg`
  MODIFY `FartRigg_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartskrog`
--
ALTER TABLE `tblzfartskrog`
  MODIFY `FartSkrog_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfarttype`
--
ALTER TABLE `tblzfarttype`
  MODIFY `FartType_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzlinktype`
--
ALTER TABLE `tblzlinktype`
  MODIFY `LinkType_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblznasjon`
--
ALTER TABLE `tblznasjon`
  MODIFY `Nasjon_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzstroket`
--
ALTER TABLE `tblzstroket`
  MODIFY `Stroket_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblztonnenh`
--
ALTER TABLE `tblztonnenh`
  MODIFY `TonnEnh_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzuser`
--
ALTER TABLE `tblzuser`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Begrensninger for dumpede tabeller
--

--
-- Begrensninger for tabell `tblfartnavn`
--
ALTER TABLE `tblfartnavn`
  ADD CONSTRAINT `FartTypeNavn` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`),
  ADD CONSTRAINT `ObjID` FOREIGN KEY (`FartObj_ID`) REFERENCES `tblfartobj` (`FartObj_ID`);

--
-- Begrensninger for tabell `tblfartobj`
--
ALTER TABLE `tblfartobj`
  ADD CONSTRAINT `FartTypeObj` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`);

--
-- Begrensninger for tabell `tblfartspes`
--
ALTER TABLE `tblfartspes`
  ADD CONSTRAINT `FartTypeSpes` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`),
  ADD CONSTRAINT `SkrogSpes` FOREIGN KEY (`SkrogID`) REFERENCES `tblverft` (`Verft_ID`),
  ADD CONSTRAINT `VerftSpes` FOREIGN KEY (`Verft_ID`) REFERENCES `tblverft` (`Verft_ID`);

--
-- Begrensninger for tabell `tblfarttid`
--
ALTER TABLE `tblfarttid`
  ADD CONSTRAINT `FartIDTid` FOREIGN KEY (`FartNavn_ID`) REFERENCES `tblfartnavn` (`FartNavn_ID`),
  ADD CONSTRAINT `NasjonIDTid` FOREIGN KEY (`Nasjon_ID`) REFERENCES `tblznasjon` (`Nasjon_ID`),
  ADD CONSTRAINT `ObjIDTid` FOREIGN KEY (`FartObj_ID`) REFERENCES `tblfartobj` (`FartObj_ID`),
  ADD CONSTRAINT `SpesIDTid` FOREIGN KEY (`FartSpes_ID`) REFERENCES `tblfartspes` (`FartSpes_ID`);

--
-- Begrensninger for tabell `tblverft`
--
ALTER TABLE `tblverft`
  ADD CONSTRAINT `NasjonIDVerft` FOREIGN KEY (`Nasjon_ID`) REFERENCES `tblznasjon` (`Nasjon_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
