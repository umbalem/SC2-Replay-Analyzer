<?php
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
?>

<?php
$MAX_FILE_SIZE = 4000000;
?>

<html>

<head>
</head>


<body>
<p><b>NOTE: this test page can only parse replays from SC2 beta phase 2</b><br />
Expect gazillion error messages if you try an older replay file.</p>
<p><b>NOTE 2: Computer opponents' events are not recorded in replays (Meaning no apm or build orders of computer opponents)</b></p>
<p>The source code and other documentation is available at <a href="https://code.google.com/p/phpsc2replay/" target=_blank>https://code.google.com/p/phpsc2replay/</a></p>
<form enctype="multipart/form-data" action="parser.php" method="POST">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $MAX_FILE_SIZE;?>" />
    Choose file to upload: <input name="userfile" type="file" /><br />
	<label for="debug">Debug?</label><input type="checkbox" name="debug" value="1" /><br />
	<label for="test">Test stuff?</label><input type="checkbox" name="test" value="1" /><br />
	<br /><br />
    <input type="submit" value="Upload File" />
</form>


<?php

if (isset($_FILES['userfile'])) {
	$error = $_FILES['userfile']['error'];
	$type = $_FILES['userfile']['type'];
	$name = $_FILES['userfile']['name'];
	$tmpname = $_FILES['userfile']['tmp_name'];
	$size = $_FILES['userfile']['size'];
	$err = false;
	if ($size >= $MAX_FILE_SIZE) {
		echo "Error: The uploaded file was too large. The maximum size is ".$MAX_FILE_SIZE." bytes.<br />";
		$err = true;
	}
	if ($error == UPLOAD_ERR_PARTIAL) {
		echo "Error: The upload was not completed successfully. Please try again.<br />";
		$err = true;
	}
	if ($error == UPLOAD_ERR_NO_FILE) {
		echo "Error: No file was selected for uploading.<br />";
		$err = true;
	}
	if (!is_uploaded_file($tmpname)) {
		echo "Error: Uploaded filename doesn't point to an uploaded file.<br />";
		$err = true;
	}
	if ($err !== true)
	{
		if ($_POST['debug'] == 1)
		{
			error_reporting(-1);
		}
		if (class_exists("MPQFile") || (include 'mpqfile.php'))
		{
			$start = microtime_float();
			$parseDurationString = "";
			$debug = 0;
			if ($_POST['debug'] == 1 || $_POST['test'] == 1)
			{
				echo sprintf("<b>Debugging is on.</b><br />\n");
				$debug = 2;
			}
			$a = new MPQFile($tmpname,true,$debug);
			$init = $a->getState();

			if (isset($_POST['test']) && $_POST['test'] == 1)
			{
				if (class_exists("SC2Replay") || (include 'sc2replay.php'))
				{

					//$bool = $a->insertChatLogMessage("testing testing", "testguy", 1);
					//$bool = $a->insertChatLogMessage("testing 2", 1, 5);

					//$a->saveAs("testfile.SC2Replay", true);
					//$a = new MPQFile("testfile.SC2Replay", true, 2);
					$byte = 0;
					$b = new SC2Replay($a);
					$b->setDebug(true);
					$tmp = $b->parseDetailsValue($a->readFile("replay.details"),$byte);
					echo "<pre>";
					var_dump($tmp);
					echo "</pre>";
					die();
				}
			}


//OUR CODE - ABOVE IS ERROR CHECKING and SUBMIT FORM
			if ($init == false)
			{
				echo "Error parsing uploaded file, make sure it is a valid MPQ archive!<br />\n";
			}
			else if ($a->getFileType() == "SC2replay")
			{
				$version = $a->getVersionString();
				echo sprintf("Version: %s<br />\n",$version);


				$b = $a->parseReplay();
				$players = $b->getPlayers();
				$recorder = $b->getRecorder();

				$gameLength = $b->getFormattedGameLength();
				$mapName = $b->getMapName();
				echo sprintf("Map name: %s, Game length: %s<br />\n",$mapName,$gameLength);


				$teamSize = $b->getTeamSize();

				if($teamSize != "1v1")
				{
					echo "ERROR: Only 1v1 games are currently supported<br />";
				}

				$gameSpeed = $b->getGameSpeedText();


				echo sprintf("Team size: %s, Game speed: %s<br />\n",teamSize, $gameSpeed);

				$gameRealm = $b->getRealm();
				echo sprintf("Realm: %s<br />\n",$gameRealm);


				//may need additional formating for DB
				$dateAndTime = $b->getCtime();
				echo sprintf("Date and time played: %s<br />\n",date('jS \of F Y \a\t H:i' ,$dateAndTime));

				if ($recorder != null)
				{
					echo sprintf("Replay recorded by: %s (EXPERIMENTAL!)<br />\n",$recorder['name']);
				}
				else
				{
					echo "Game Recorder Unknown<br />";
				}

				$obsString = "";
				$obsCount = 0;
				$playerCount = 0;

				echo "<br />Player Info<br />";
				//get player info
				foreach($players as $value)
				{
					if($value['isObs'])
					{
						if ($obsString == "")
						{
							$obsString = $value['name'];
						}
						else
						{
							$obsString .= ', '.$value['name'];
						}

						$obsCount++;
						continue;
					}

					if(!$b->isWinnerKnown())
					{
						echo "ERROR: Winner Unknown<br />";
					}

					echo "NOTE: Comp Difficuly printed here.....<br />";

					if ($value['isComp'] && $b->getTeamSize() !== null)
					{
						$difficultyString = sprintf(" (%s)",SC2Replay::$difficultyLevels[$value['difficulty']]);
						echo sprintf("Difficulty Level: %s <br />", $difficultyString);
					}
					else
					{
					    //playerCount = player num
						$name = $value['name'];
						$race = $value['race'];
						$color = $value['color'];
						$sColor = $value['sColor']; //color string

						if(!$b->isWinnerKnown())
						{
							echo "ERROR: winner unknown<br />";
						}

						if (!$value['isObs'] && $value['ptype'] != 'Comp')
						{
							$avgApm = round($value['apmtotal'] / ($b->getGameLength()/ 60));

							echo sprintf("AVG AMP (EXPEREMENTAL): %d<br />\n",$avgApm);
							echo "TODO: APM array (GRAPH SUPPORT)<br />";
						}

						echo sprintf("Player: %d<br />\n",$playerCount);
						echo sprintf("Name: %s<br />\n",$name);
						echo sprintf("Race: %s<br />\n",$race);
						echo sprintf("Color: %s<br />\n",$color);
						echo sprintf("sColor: %s<br />\n",$sColor);
						echo sprintf("Winner: %d<br />\n", $value['won']);

						$playerCount++;
					}

					echo "<br />";
				}


				echo "Observers: <br />";
				if($obsCount > 0)
				{
					echo "Observers ($obsCount): $obsString<br />\n";
				}

				echo "<br />";

				echo "Total unique ability events: <br />";
				echo "Some events appear to be unknown <br />";
				echo "Appears to be no way to determine who did what <br />";
				echo "Appears to be no way determine race <br />";

				$temp = $b->getUnits();
				if (count($temp) > 0)
				{
					foreach ($temp['units'] as $uType => $uId)
					{
						$unitArray = $b->getUnitArray($uType, $build);
						echo sprintf("%s: %d\n <br />",$unitArray['name'],count($uId));
					}
				}


				echo "<br />";
			    echo "EVENTS: <br />";
				echo "<br />";

				$t = $b->getEvents();
				//create table of all events
				$pNum = count($players);

				echo sprintf("%s (%s)<br />",$value['name'],$value['race']);

				echo "TODO parse events into useable form (function works just have to format output..)<br />";
				echo "Protocol needs to support multiple player(player 1 player 2) events per second, (unit,unit,unit)players can have mulitple events in a second<br />";

/*
				if (count($t) > 0)
				{
					foreach ($t as $value)
					{

						$eventarray = $b->getAbilityArray($value['a']);
						// setting rally points or issuing move/attack move or other commands does not tell anything - NOT MY COMMENT...
						if ($eventarray['type'] == SC2_TYPEGEN && !isset($_POST['debug'])) continue;


						echo sprintf("%d sec",$value['t'] / 16);

						foreach ($players as $value2)
						{
							if ($value2['isObs'] || $value2['ptype'] == 'Comp') continue;
							if ($value['p'] == $value2['id'])
							{
								echo sprintf(" %s %s ",$eventarray['desc'],(isset($_POST['debug']))?sprintf(" (%06X)",$value['a']):"");
							}
							else
							{
								echo "";
							}
						}
						echo "<br />\n";
					}
				}
*/

				echo "<br />";
				echo "There is array for each event type: building-unit-upgrades (not tested)<br />";

				//array for each type
				foreach ($players as $value)
				{
					if ($value['isComp'] || $value['isObs']) continue;

					foreach ($value['firstevents'] as $eventid => $time)
					{
						$eventarray = $b->getAbilityArray($eventid);
						$str = sprintf("%s %s %d \n",
									$eventarray['name'],
									$b->getFormattedSecs($time),
								    $value['numevents'][$eventid]);

						switch ($eventarray['type'])
						{
							case SC2_TYPEBUILDING:
							case SC2_TYPEBUILDINGUPGRADE:
								$buildingTable .= $str;
								break;
							case SC2_TYPEUNIT:
							case SC2_TYPEWORKER:
								$unitTable .= $str;
								break;
							case SC2_TYPEUPGRADE:
								$upgradeTable .= $str;
								break;
							default:
						}
					}
				}

				//echo "<br />\n";
			}
			else if ($a->getFileSize("DocumentHeader") > 0 && $a->getFileSize("Minimap.tga") > 0)
			{
				// possibly SC2 map file
				//ADD MAP TO THE PARSER IF NEW
				if (class_exists("SC2Map") || (include 'sc2map.php'))
				{
					$sc2map = new SC2Map();
					$sc2map->parseMap($a);
					echo "<table>";
					echo sprintf("<tr><td>Map name:</td><td>%s</td></tr>\n",$sc2map->getMapName());
					echo sprintf("<tr><td>Author:</td><td>%s</td></tr>\n",$sc2map->getAuthor());
					echo sprintf("<tr><td>Short description:</td><td>%s</td></tr>\n",preg_replace('/<[^>]+>/','',$sc2map->getShortDescription()));
					echo sprintf("<tr><td>Long description:</td><td>%s</td></tr>\n",preg_replace('/<[^>]+>/','',$sc2map->getLongDescription()));
					$minimapfilename = md5($sc2map->getMapName()).".png";
					imagepng($sc2map->getMiniMapData(),$minimapfilename);
					echo sprintf("</table>Minimap:<br /><img src=\"$minimapfilename\" /><br />\n");
				}
			}
		}
	}
}


?>


</body>
</html>