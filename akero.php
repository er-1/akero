<?php
$timestart = microtime(true);

define("SIZE",          50);
define("NBBUS",         3);
define("OD_KEY_DECAUX", "efc83baf431770b1066c383c7b4a33aea3bf5057");
define("OD_KEY_TISSEO", "46fef012-016c-4a40-98a8-bf7ffbbc186d");
 
$BUS = array(
    "22" => array(
        "id" => "11821949021891631",
        "color" => "#aaaaaa",
        "stops" => array(
            "Corraze" => array(
                "Marengo-SNCF" => array(
                    "id" => "3377699720880679",
                    "when" => array(),
                    "HL" => array(17, 18),
                ),
            ),
        ),
    ),
    "23" => array(
        "id" => "11821949023193145",
        "color" => "#aaaaaa",
        "stops" => array(
            "Achiary" => array(
                "Rangueil" => array(
                    "id" => "3377699720881223",
                    "when" => array(),
                    "HL" => array(8, 9),
                ),
            ),
            "Assalit" => array(
                "Jeanne d'Arc" => array(
                    "id" => "3377699720883038",
                    "when" => array(),
                    "HL" => array(),
                ),
                "Rangueil"     => array(
                    "id" => "3377699720883037",
                    "when" => array(),
                    "HL" => array(8, 9),
                ),
            ),
            "Capdenier" => array(
                "Jeanne d'Arc" => array(
                    "id" => "3377699723785428",
                    "when" => array(),
                    "HL" => array(17, 18),
                ),
            ),
            "Jean Jaurès" => array(
                "Rangueil" => array(
                    "id" => "3377699721881197",
                    "when" => array(),
                    "HL" => array(),
                ),
            ),
        ),
    ),
    "10" => array(
        "id" => "11821949021891619",
        "color" => "#aaaaaa",
        "stops" => array(
            "Bajac" => array(
                "Cours Dillon" => array(
                    "id" => "3377699720884480",
                    "when" => array(),
                    "HL" => array(17, 18),
                ),
            ),
        ),
    ),
    "L1" => array(
        "id" => "11821953316814883",
        "color" => "#aaaaaa",
        "stops" => array(
            "Jean Jaurès" => array(
                "Gymnase de L'Hers" => array(
                    "id" => "3377699721902047",
                    "when" => array(),
                    "HL" => array(),
                ),
            ),
        ),
    ),
);

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

function getJSON($url) {
    $content = file_get_contents($url);
    if ($content === false)
        return array();
    return json_decode($content, true);
}

function printLine($logo, $from, $to, $when, $hl = false) {
    print("<div class=\"data\">\n");
    print($logo."\n");
	print("<div class=\"where\">\n");
	printf("<div class=\"from\">%s</div>\n", $from);
	printf("<div class=\"to\">%s</div>\n", $to);
	print("</div>\n");
    $s ="";
    if ($hl) {
        $s = "style=\"color: #ffffff;\"";
    }
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
    }
    printLine(
        "<div class=\"velo\"><img src=\"velo.ico\"></div>",
        $station,
        sprintf("%d", $id),
        $txt
    );
}

function printBus($line, $stop, $destination) {
    global $BUS;

    $w = array();
    foreach ($BUS[$line]["stops"][$stop][$destination]["when"] as $v)
        $w[] = substr($v, 11, 5);
    printLine(
        sprintf("<div class=\"bus\" style=\"background-color: %s;\">%s</div>", $BUS[$line]["color"], $line),
        $stop,
        $destination,
        implode(" ", $w),
        in_array(intval(date("G")), $BUS[$line]["stops"][$stop][$destination]["HL"])
    );
}

function getBus($nb = NBBUS) {
    global $BUS;
    
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

?>
<!doctype html>
<html> 
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AKERO</title>
<style>
body {
    font-family: sans-serif;
    background-color: #002b36;
    color: #839496;
}
.section, .data {
    width: 95%;
    padding: 1px;
}
.section {
    margin : auto;
}
.data {
    width: 100%;
    height: <?php print(SIZE); ?>px;
    line-height: <?php print(SIZE); ?>px;
    max-height: <?php print(SIZE); ?>px;
    margin: 0px;
    border-top: 1px solid #ccc;
    padding: 2px;
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
    font-size: 140%;
}
.velo {
    background-color: #b22615;
}
.where {
    width: 100px;
    margin-left: 5px;
}
.when {
    text-align: left;
    vertical-align: middle;
    margin-left: 5px;
    white-space: nowrap;
    overflow: hidden;
    font-size: 110%;
}
.from, .to {
    height: <?php print(SIZE / 2); ?>px;
    line-height: <?php print(SIZE / 2); ?>px;
    max-height: <?php print(SIZE / 2); ?>px;
    margin: 0px;
    padding: 0px;
    display: block;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.from {
    font-size: 115%;
    color: #A3B4B6;
}
.to {
    font-size: 75%;
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
getBus();
?>
<div class="section">
<?php
printBus("23", "Assalit", "Rangueil");
printBus("23", "Achiary", "Rangueil");
printBus("23", "Capdenier", "Jeanne d'Arc");
printBus("10", "Bajac", "Cours Dillon");
printBus("22", "Corraze", "Marengo-SNCF");
printBus("23", "Assalit", "Jeanne d'Arc");
printBus("23", "Jean Jaurès", "Rangueil");
printBus("L1", "Jean Jaurès", "Gymnase de L'Hers");
printBike(214, "Achiary");
printBike(211, "Dormeur");
?>
</div>
<?php
print('<p style="font-size:55%;">Donn&eacute;es Tiss&eacute;o (license <a href="https://imsva91-ctp.trendmicro.com/wis/clicktime/v1/query?url=http%3a%2f%2fdata.toulouse%2dmetropole.fr%2fla%2dlicence%29.&umid=8C995A8E-3FEC-2E05-B7BF-47E8C000740D&auth=f2b449ca55614c68587e7992fdd805265a7028cb-7ae7d71587d3daaa0cc2462521d5dfdc704f56a0">ODbl</a>) + Donn&eacute;es JCDecaux</p>');
print('<p style="font-size:50%;">Generated in '.(microtime(true) - $timestart).' seconds::::<a href="https://github.com/er-1/akero">GitHub</a></p>');
?>
</body>
</html>
