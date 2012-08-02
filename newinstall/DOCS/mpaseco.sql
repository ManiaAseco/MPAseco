-- Database: `mpaseco`
--
-- --------------------------------------------------------

--
-- Tablestructure for Table `maps`
--

CREATE TABLE IF NOT EXISTS `maps` (
  `Id` mediumint(9) NOT NULL auto_increment,
  `Uid` varchar(27) NOT NULL default '',
  `Name` varchar(100) NOT NULL default '',
  `Author` varchar(30) NOT NULL default '',
  `Environment` varchar(10) NOT NULL default '',
  PRIMARY KEY (`Id`),
  UNIQUE KEY `Uid` (`Uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablestructure for Table `players`
--

CREATE TABLE IF NOT EXISTS `players` (
  `Id` mediumint(9) NOT NULL auto_increment,
  `Login` varchar(50) NOT NULL default '',
  `Game` varchar(3) NOT NULL default '',
  `NickName` varchar(100) NOT NULL default '',
  `Nation` varchar(3) NOT NULL default '',
  `UpdatedAt` datetime NOT NULL default '0000-00-00 00:00:00',
  `Wins` mediumint(9) NOT NULL default 0,
  `TimePlayed` int(10) unsigned NOT NULL default 0,
  `TeamName` char(60) NOT NULL default '',
  `Respawns` mediumint(9) unsigned NOT NULL default 0,
  `Deaths` mediumint(9) unsigned NOT NULL default 0,
  `Hits` mediumint(9) unsigned NOT NULL default 0,
  `GotHits` mediumint(9) unsigned NOT NULL default 0,
  `Captures` mediumint(9) unsigned NOT NULL default 0,  
  PRIMARY KEY (`Id`),
  UNIQUE KEY `Login` (`Login`),
  KEY `Game` (`Game`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablestructure for Table `records`
--

CREATE TABLE IF NOT EXISTS `records` (
  `Id` int(11) NOT NULL auto_increment,
  `MapId` mediumint(9) NOT NULL default 0,
  `PlayerId` mediumint(9) NOT NULL default 0,
  `Score` int(11) NOT NULL default 0,
  `Date` datetime NOT NULL default '0000-00-00 00:00:00',
  `Checkpoints` text NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `PlayerId` (`PlayerId`,`MapId`),
  KEY `MapId` (`MapId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablestructure for Table `players_extra`
--

CREATE TABLE IF NOT EXISTS `players_extra` (
  `PlayerId` mediumint(9) NOT NULL default 0,
  `Cps` smallint(3) NOT NULL default -1,
  `DediCps` smallint(3) NOT NULL default -1,
  `Donations` mediumint(9) NOT NULL default 0,
  `Style` varchar(20) NOT NULL default '',
  `Panels` varchar(255) NOT NULL default '',
  `PanelBG` varchar(30) NOT NULL default '',
  PRIMARY KEY (`PlayerId`),
  KEY `Donations` (`Donations`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablestructure for Table `rs_karma`
--

CREATE TABLE IF NOT EXISTS `rs_karma` (
  `Id` int(11) NOT NULL auto_increment,
  `MapId` mediumint(9) NOT NULL default 0,
  `PlayerId` mediumint(9) NOT NULL default 0,
  `Score` tinyint(3) NOT NULL default 0,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `PlayerId` (`PlayerId`,`MapId`),
  KEY `MapId` (`MapId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablestructure for Table `rs_rank`
--

CREATE TABLE IF NOT EXISTS `rs_rank` (
  `PlayerId` mediumint(9) NOT NULL default 0,
  `Avg` float NOT NULL default 0,
  KEY `PlayerId` (`PlayerId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablestructure for Table `rs_times`
--

CREATE TABLE IF NOT EXISTS `rs_times` (
  `Id` int(11) NOT NULL auto_increment,
  `MapId` mediumint(9) NOT NULL default 0,
  `PlayerId` mediumint(9) NOT NULL default 0,
  `Score` int(11) NOT NULL default 0,
  `Date` int(10) unsigned NOT NULL default 0,
  `Checkpoints` text NOT NULL,
  PRIMARY KEY (`Id`),
  KEY `PlayerId` (`PlayerId`,`MapId`),
  KEY `MapId` (`MapId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
