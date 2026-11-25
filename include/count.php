<!DOCTYPE html>
<html lang="fr">
<?php  
$progname = basename($_SERVER['SCRIPT_FILENAME'], ".php");
include_once 'include/config.php';
?>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>TG COUNTER F62DMR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Architects+Daughter&family=Fredoka+One&family=Tourney&family=Oswald&display=swap" rel="stylesheet">
<script type="text/javascript" src="scripts/hbmon.js"></script>
<link rel="stylesheet" type="text/css" href="css/styles.php" />
<meta http-equiv="refresh" content="120">
</head>
<body style="background-color: transparent;font: 10pt arial, sans-serif;">
<center><div style="width:1100px; text-align: center; margin-top:5px;"></div>
<div style="width: 800px;">
<p style="text-align:center;"><span style="color:blue;font-size: 18px; font-weight:bold;">Les groupes de discussion les plus utilisés le <?php  
    // Affiche la date du jour  
    echo date_fran();
?></span></p>

<?php  
function date_fran()
{
    $mois = array("janvier", "fevrier", "mars",
                  "avril","mai", "juin", 
                  "juillet", "août","septembre",
                  "octobre", "novembre", "decembre");
    $jours= array("dimanche", "lundi", "mardi",
                  "mercredi", "jeudi", "vendredi",
                  "samedi");
    return $jours[date("w")]." ".date("j").(date("j")==1 ? "er ":" ").
           $mois[date("n")-1]." ".date("Y");
}
?>                           
<p>
</div>
<!-- TG table -->
<div style="width: 1100px; margin-left:0px; center;">
<fieldset style="background-color:#FFFFFF; width:500px; margin: 0 auto; font-size:14px; border: 2px solid black; border-radius: 10px;">
<style>
table, th, td {
  border: 1px solid black; /* Bordure des cellules du tableau */
  border-collapse: collapse;
}
</style>
<table style="text-align: center; margin-top:5px; table-layout:fixed; font: 10pt arial, sans-serif;background-color: #f9f9f9;">
    <tr class="theme_color" style="background-color:#337ab7; color:white; height: 32px;font: 11pt tahoma, sans-serif;">
        <th style='width: 70px;'><b>TG</b></th>
        <th style='width: 150px;'><b>Nom du <br>Talk Group</b></th>
        <th style='width: 100px;'><b>Nombre <br>d'échanges</b></th>
        <th style='width: 140px;'><b>Durée totale <br>des QSOs</b></th>
        <th style='width: 300px;'><b>Les plus bavards</b></th>
    </tr>
    <tr>
        <td>&nbsp;<b>272</b>&nbsp;</td>
        <td>&nbsp;<b>Ireland </b>&nbsp;</td>
        <td>&nbsp;<b>9</b>&nbsp;</td>
        <td>&nbsp;<b>13 minutes 15s</b>&nbsp;</td>
        <td>&nbsp;<b>29CT067 - N0CALL</b>&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;<b>216</b>&nbsp;</td>
        <td>&nbsp;<b>Hungary </b>&nbsp;</td>
        <td>&nbsp;<b>26</b>&nbsp;</td>
        <td>&nbsp;<b>10 minutes 36s</b>&nbsp;</td>
        <td>&nbsp;<b>N0CALL - N0CALL - N0CALL</b>&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;<b>265</b>&nbsp;</td>
        <td>&nbsp;<b>Deutschland </b>&nbsp;</td>
        <td>&nbsp;<b>38</b>&nbsp;</td>
        <td>&nbsp;<b>7 minutes 55s</b>&nbsp;</td>
        <td>&nbsp;<b>N0CALL - N0CALL - N0CALL</b>&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;<b>5962</b>&nbsp;</td>
        <td>&nbsp;<b>Multi-Protocol </b>&nbsp;</td>
        <td>&nbsp;<b>9</b>&nbsp;</td>
        <td>&nbsp;<b>2 minutes 19s</b>&nbsp;</td>
        <td>&nbsp;<b>REFL59</b>&nbsp;</td>
    </tr>
</table>
</fieldset>
</div>
</body>
</html>