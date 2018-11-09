<?php
$timestart = microtime(true);
$midori = stripos($_SERVER["HTTP_USER_AGENT"], "midori") !== False;

//error_reporting(E_ALL);
//ini_set('display_errors', 'On');
//ini_set('log_errors', 'On');

if ($midori)
    define("SIZE", 150);
else
    define("SIZE", 50);
define("NBBUS",         3);
define("BUS_CONF",      ".tisseo");
define("DEFAULT_COLOR", "#aaaaaa");
define("OD_KEY_DECAUX", "<your key from Decaux>");
define("OD_KEY_TISSEO", "<your key from Tisseo>");

define("CONFIG", array(
    array("Corraze",     "Marengo-SNCF",             array(17, 18)),
    array("Achiary",     "Université Paul Sabatier", array(7, 8, 9)),
    array("Assalit",     "Jeanne d'Arc",             array()),
    array("Assalit",     "Université Paul Sabatier", array(7, 8, 9)),
    array("Baroux",      "Université Paul Sabatier", array(7, 8, 9)),
    array("Capdenier",   "Jeanne d'Arc",             array(17, 18)),
    array("Jean Jaurès", "Université Paul Sabatier", array()),
    array("Bajac",       "Cours Dillon",             array(17, 18)),
    array("Jean Jaurès", "Gymnase de L'Hers",        array())
));

////////////////////////////////////////////////////////////////////////////////

if (stripos($_SERVER['HTTP_USER_AGENT'], "google") !== false) {
?>
    <html>
    <head>
	<meta name="robots" content="noindex,nofollow">
	<meta name="googlebot" content="noindex,nofollow,noarchive,nosnippet">
	<title>EMPTY</title>
	</head>
	<body>EMPTY</body>
	</html>
<?php
    exit(0);
}

$BUS = array();

function getCache() {
    global $BUS;

    if (count($BUS) > 0) return;
    $file = @file(BUS_CONF);
    if ($file !== false) {
        $s = "";
        foreach($file as $l)
            $s .= $l;
        $BUS = json_decode($s, true);
    }
}

function saveCache() {
    global $BUS;

    $file = fopen(BUS_CONF, "w");
    fwrite($file, json_encode($BUS, JSON_PRETTY_PRINT));
    fclose($file);
}

function getJSON($url) {
    $content = file_get_contents($url);
    if ($content === false)
        return array();
    return json_decode($content, true);
}

function printLine($logo, $from, $to, $when, $hl = false, $eta = false) {
    global $midori;

    print("<div class=\"data\">\n");
    print($logo."\n");
	print("<div class=\"where\">\n");
	printf("<div class=\"from\">%s</div>\n", $from);
	printf("<div class=\"to\">%s</div>\n", $to);
	print("</div>\n");
    $s ="";
    if ($hl) $s .= "color:#ffffff;";
    if ($eta)
        if (! $midori)
            $s .= "font-size:200%;";
        else
            $s .= "font-size:600%;font-weight:bold;";
    if (strlen($s) > 0) $s = "style=\"$s\"";
	printf("<div class=\"when\" %s>%s</div>\n", $s, $when);
    print("</div>\n");
}

function printBike($id, $station) {
    $data = getJSON("https://api.jcdecaux.com/vls/v1/stations/".$id."?contract=Toulouse&apiKey=".OD_KEY_DECAUX);
    $txt = "";
    if (count($data) <= 0) {
        $txt = "no data";
    } else {
        $v = $data['available_bikes'];
        switch ($v) {
        case 0:
            $txt = "no bike";
            break;
        case 1:
            $txt = "1 bike";
            break;
        default:
            $txt = sprintf("%d bikes", $v);
        }
        $txt = "&nbsp;&nbsp;" . $txt;
    }
    printLine(
        "<div class=\"velo\"><img src=\"velo.ico\"></div>",
        $station,
        sprintf("%d", $id),
        $txt
    );
}

function printBus($stop, $destination, $eta = false) {
    global $BUS;

    $line = "";
    foreach($BUS as $k => $v) {
        if (!array_key_exists($stop, $v["stops"])) continue;
        if (!array_key_exists($destination, $v["stops"][$stop])) continue;
        $line = $k;
        break;
    }
    if (! $line) return;
    $now = intval(date("G")) * 60 + intval(date("i"));
    $w = array();
    foreach ($BUS[$line]["stops"][$stop][$destination]["when"] as $v) {
        $str = substr($v, 11, 5);
        if ($eta) {
            $str = intval(substr($str, 0, 2)) * 60 + intval(substr($str, 3, 2));
            if ($str >= $now)
                $str = $str - $now;
            else
                $str = 24 * 60 - $now + $str;
            if ($str >= 100)
                $str = "--";
            else
                if ($str < 10) $str = sprintf("&nbsp;%d", $str);
        }
        $w[] = $str;
    }
    printLine(
        sprintf("<div class=\"bus\" style=\"background-color: %s;\">%s</div>", $BUS[$line]["color"], $line),
        $stop,
        $destination,
        implode(" ", $w),
        in_array(intval(date("G")), $BUS[$line]["stops"][$stop][$destination]["HL"]),
        $eta
    );
}

function getBus($nb = NBBUS) {
    global $BUS;

    if (count($BUS) <= 0) return;
    $v = array();
    foreach ($BUS as $b) {
        $lid = $b["id"];
        foreach ($b["stops"] as $s) {
            foreach ($s as $d) {
                $v[] = $d["id"]."|".$lid;
            }
        }
    }
    $data = getJSON("https://api.tisseo.fr/v1/stops_schedules.json?&stopsList=".implode(",", $v)."&timetableByArea=1&number=".$nb."&key=".OD_KEY_TISSEO);
    foreach ($data["departures"]["stopAreas"] as $k)
        foreach ($k["schedules"] as $d) {
            $BUS[$d["line"]["shortName"]]["color"] = $d["line"]["bgXmlColor"];
            foreach ($d["journeys"] as $t)
                $BUS[$d["line"]["shortName"]]["stops"][$d["stop"]["name"]][$d["destination"]["name"]]["when"][] = $t["dateTime"];
        }
}

// 0: config to cache (and phone display)
// 1: phone display (default)
// 2: Pi display
$what = 1;
if (array_key_exists("K", $_REQUEST)) {
    $str = chop($_REQUEST["K"]);
    if (($str == 0) || ($str == 2)) $what = $str;
}
if ($what == 0) {
    $stops = getJSON("https://api.tisseo.fr/v1/stop_areas.json?&key=".OD_KEY_TISSEO);
    foreach(CONFIG as $v) {
        $id = "";
        foreach($stops["stopAreas"]["stopArea"] as $s) {
            if ($s["name"] != $v[0]) continue;
            $id = $s["id"];
            break;
        }
        if (! $id) continue;
        $data = getJSON("https://api.tisseo.fr/v1/stops_schedules.json?&stopsList=".$id."&timetableByArea=1&key=".OD_KEY_TISSEO);
        foreach($data["departures"]["stopAreas"][0]["schedules"] as $s) {
            if ($s["destination"]["name"] != $v[1]) continue;
            $line = $s["line"]["shortName"];
            $color = $s["line"]["color"];
            $lineid = $s["line"]["id"];
            $stopid = $s["stop"]["id"];
            if (! array_key_exists($line, $BUS))
                $BUS[$line] = array(
                    "id" => $lineid,
                    "color" => $color,
                    "stops" => array()
                );
            if (! array_key_exists($v[0], $BUS[$line]["stops"]))
                $BUS[$line]["stops"][$v[0]] = array();
            if (! array_key_exists($v[1], $BUS[$line]["stops"][$v[0]]))
                $BUS[$line]["stops"][$v[0]][$v[1]] = array(
                    "id" => $stopid,
                    "when" => array(),
                    "HL" => $v[2]
                );
            break;
        }
    }
    saveCache();
    $what = 1;
}
?>
<!doctype html>
<html> 
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="60;">
<title>AKERO</title>
<style>
body {
    font-family: sans-serif;
    background-color: #002b36;
    color: #839496;
}
<?php if (! $midori) { ?>
.section, .data {
    width: 95%;
    padding: 1px;
}
.section {
    margin : auto;
}
<?php } ?>
.data {
    width: 100%;
    height: <?php print(SIZE); ?>px;
    line-height: <?php print(SIZE); ?>px;
    max-height: <?php print(SIZE); ?>px;
    margin: 0px;
    border-top: 1px solid #ccc;
    padding: 2px;
    position: relative;
}
.bus, .velo, .where, .when {
    height: <?php print(SIZE); ?>px;
    line-height: <?php print(SIZE); ?>px;
    max-height: <?php print(SIZE); ?>px;
    margin: 0px;
    padding: 0px;
    display: block;
    float: left;
 }
.bus, .velo {
    color: #eeeeee;
    width: <?php print(SIZE); ?>px;
    text-align: center;
    vertical-align: middle;
<?php if (! $midori) { ?>
    font-size: 140%;
<?php } else { ?>
    font-size: 420%;
    font-weight: bold;
<?php } ?>
}
.velo {
    background-color: #b22615;
}
.where {
    opacity: 0.5;
<?php if (! $midori) { ?>
    margin-left: 5px;
<?php } else { ?>
    margin-left: 15px;
<?php } ?>
}
@font-face {
    font-family: "mymenlo";
    src: url(menlo.ttf) format("truetype");
}
.when {
    font-family: mymenlo;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
    position: absolute;
<?php if (! $midori) { ?>
    font-size: 120%;
    left: <?php print(SIZE + 80); ?>px;
<?php } else { ?>
    font-size: 340%;
    left: <?php print(SIZE + 240); ?>px;
<?php } ?>
}
.from, .to {
    margin: 0px;
    padding: 0px;
    display: block;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
}
.from {
    height: <?php print(SIZE * 0.6); ?>px;
    line-height: <?php print(SIZE * 0.6); ?>px;
    max-height: <?php print(SIZE * 0.6); ?>px;
<?php if (! $midori) { ?>
    font-size: 130%;
<?php } else { ?>
    font-size: 360%;
<?php } ?>
    color: #A3B4B6;
}
.to {
    height: <?php print(SIZE * 0.4); ?>px;
    line-height: <?php print(SIZE * 0.4); ?>px;
    max-height: <?php print(SIZE * 0.4); ?>px;
<?php if (! $midori) { ?>
    font-size: 70%;
<?php } else { ?>
    font-size: 220%;
<?php } ?>
    color: #738486;
}
p {
    text-align: center;
    margin-bottom: 0px;
    margin-top: 3px;
}
a {
    text-decoration: none;
    color: #ABBCBE;
}
</style>
</head>
<body>
<?php
getCache();
getBus();
?>
<div class="section">
<?php
if ($what == 1) {
    printBus("Assalit",     "Université Paul Sabatier");
    printBus("Achiary",     "Université Paul Sabatier");
    printBus("Baroux",      "Université Paul Sabatier");
    printBus("Capdenier",   "Jeanne d'Arc");
    printBus("Bajac",       "Cours Dillon");
    printBus("Corraze",     "Marengo-SNCF");
    printBus("Assalit",     "Jeanne d'Arc");
    printBus("Jean Jaurès", "Université Paul Sabatier");
    printBus("Jean Jaurès", "Gymnase de L'Hers");
    printBike(214, "Achiary");
    printBike(211, "Dormeur");
}
if ($what == 2) {
    printBus("Assalit", "Université Paul Sabatier", true);
    printBus("Achiary", "Université Paul Sabatier", true);
    printBus("Assalit", "Jeanne d'Arc",             true);
}
?>
</div>
<?php
if ($what != 2) {
?>
    <p style="font-size:55%;">Donn&eacute;es Tiss&eacute;o (license <a href="https://imsva91-ctp.trendmicro.com/wis/clicktime/v1/query?url=http%3a%2f%2fdata.toulouse%2dmetropole.fr%2fla%2dlicence%29.&umid=8C995A8E-3FEC-2E05-B7BF-47E8C000740D&auth=f2b449ca55614c68587e7992fdd805265a7028cb-7ae7d71587d3daaa0cc2462521d5dfdc704f56a0">ODbl</a>) + Donn&eacute;es JCDecaux</p>
    <p style="font-size:50%;">Generated in <?php print(microtime(true) - $timestart); ?> seconds::::<a href="https://github.com/er-1/akero">GitHub</a></p>
<?php
}
?>
</body>
</html>
