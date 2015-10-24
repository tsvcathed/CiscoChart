<?php

// Fetch values from HTTP GET's and append to Variables
$snmp = $_GET['snmp'];
$ip = $_GET['ip'];
$community = $_GET['community'];
$interface = $_GET['interface'];
$oid0 = $_GET['oid0'];
$oid1 = $_GET['oid1'];
$refresh = $_GET['refresh'];

// Set defaults
if (empty($community)) { $community = "tceo"; }
if (empty($refresh)) { $refresh = "10"; }

// Change seconds to Milliseconds for setInterval loop
$chartloop = $refresh * 1000;

// Set Bandwidth OID's by Numerical Interface Choice
if (!empty($interface)) {
    $oid0 = "1.3.6.1.2.1.2.2.1.10.$interface";
    $oid1 = "1.3.6.1.2.1.2.2.1.16.$interface";
}

// Set PHP-SNMP Default Variables
snmp_set_oid_numeric_print(1);
snmp_set_quick_print(TRUE);
snmp_set_enum_print(TRUE);
snmp_set_valueretrieval(SNMP_VALUE_PLAIN);

// Get OID Data if Requested
if (!empty($oid0)) {
    $output0 = snmpget($ip, $community, $oid0);
}
if (!empty($oid1)) {
  $output1 = snmpget($ip, $community, $oid1);
}

// Generate HTML Head Function
function head() {
    echo "<!DOCTYPE html>
    <html>
    <head>
      <meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\">
      <title>Jordan's Live Bandwidth Grapher</title>
      <style>
      body {
        background-color: #F5F5F5;
        font-family: Monaco, Menlo, Consolas, \"Courier New\", monospace;
      }
      </style>
    </head>";
}

// Generate Menu Function
function menu() {
    echo "<div>
        <form method=\"get\" action=\"\">";
    if ( empty($GLOBALS[enumint])) {
        echo "Router IP: <input type=\"text\" name=\"ip\" id=\"ip\" value=\"$GLOBALS[ip]\"/>
        Community: <input type=\"text\" name=\"community\" id=\"community\" value=\"$GLOBALS[community]\"/>";
        }
    else { // Disable form entry for IP and Community once Interfaces have been enumerated
        echo "Router IP: <input type=\"text\" name=\"ip\" id=\"ip\" value=\"$GLOBALS[ip]\" readonly=\"readonly\"/>
              Community: <input type=\"text\" name=\"community\" id=\"community\" value=\"$GLOBALS[community]\" readonly=\"readonly\"/>";
    } 
    if ( !empty($GLOBALS[enumint])) { // Generate Dropdown list of Interfaces if the Interface Enumeration has happened
        echo " Interface: <select name=\"interface\">";
        for ( $i=1; $i<$GLOBALS[enumint]+2; $i++) {
            $valint = snmpget($GLOBALS[ip], $GLOBALS[community], "1.3.6.1.2.1.2.2.1.2.$i");
            $valintnm = snmpget($GLOBALS[ip], $GLOBALS[community], "1.3.6.1.2.1.31.1.1.1.18.$i");
            if ( !empty($valint) ) {
                if ( "$GLOBALS[interface]" == $i ) {
                    echo "<option value=\"$i\" selected>$valint $valintnm</option>";
                }
                else {
                    echo "<option value=\"$i\">$valint $valintnm</option>";
                }
            }
        }
        echo "</select>
            <input type=\"submit\" id=\"submit\" value=\"Submit\"/>
            <input type=\"reset\" id=\"reset\" onclick=\"window.location='?';\" value=\"Reset\"/>
            </form>
            </div>
            <br />";
    }
    else {
        echo "
            <input type=\"submit\" id=\"submit\" value=\"List Interfaces\"/>
            </form>
            </div>";
    }
}

// Append Footer Function to close page
function foot() {
    echo "</body>
        </html>";
}

// Check if all required values are available before generating Chart
if (empty($snmp) && !empty($ip) && !empty($oid0) && !empty($oid1) && !empty($community) && !empty($refresh)) {
    $enumint = snmpget($ip, $community, "1.3.6.1.2.1.2.1.0");
    $routernm = snmpget($ip, $community, "1.3.6.1.2.1.1.5.0");
    head();
    menu();
    echo "<script type=\"text/javascript\" src=\"http://code.jquery.com/jquery-1.9.1.js\"></script>
    <script type=\"text/javascript\">
    $(function () {
        $(document).ready(function() {
            Highcharts.setOptions({
                global: {
                    useUTC: false
                }
            });
            var chart;
            $('#container').highcharts({
               credits: {
                    enabled: false
                },
                chart: {
                    type: 'areaspline',
                    animation: Highcharts.svg,
                    marginRight: 10,
                    events: {
                        load: function() {
                            var series0 = this.series[0],
                                series1 = this.series[1];
                                // Set initial variables
                                $.ajax({
                                type: \"GET\",
                                cache: false,
                                url: \"?snmp=true&ip=$ip&community=$community&oid0=$oid0\",
                                dataType: \"text\",
                                success: function(output0) {
                                    previousval0 = parseInt(output0);
                                    }
                                });
                                // Set initial variables
                                $.ajax({
                                type: \"GET\",
                                cache: false,
                                url: \"?snmp=true&ip=$ip&community=$community&oid1=$oid1\",
                                dataType: \"text\",
                                success: function(output1) {
                                    previousval1 = parseInt(output1);
                                    }
                                });                                
                            setInterval(function() {
                                var x = (new Date()).getTime();
                                $.ajax({
                                type: \"GET\",
                                cache: false,
                                url: \"?snmp=true&ip=$ip&community=$community&oid0=$oid0\",
                                dataType: \"text\",
                                success: function(output0) {
                                    var y0 = ((parseInt(output0) - previousval0)/1024/1024*8)/$refresh;
                                    previousval0 = parseInt(output0);
                                    series0.addPoint([x, y0], true, true);
                                    }
                                });
                                $.ajax({
                                type: \"GET\",
                                cache: false,
                                url: \"?snmp=true&ip=$ip&community=$community&oid1=$oid1\",
                                dataType: \"text\",
                                success: function(output1) {
                                    var y1 = ((parseInt(output1) - previousval1)/1024/1024*8)/$refresh;
                                    previousval1 = parseInt(output1);
                                    series1.addPoint([x, y1], true, true);
                                    }
                                });
                            }, $chartloop);
                        }
                    }
                },
                title: {
                    text: 'Live Ingress/Egress Bandwidth for $routernm'
                },
                xAxis: {
                    type: 'datetime',
                    minTickInterval: 1
                },
                yAxis: {
                    min: '0',
                    title: {
                        text: 'mbps'
                    },
                    plotLines: [{
                        value: 0,
                        width: 1,
                        color: '#808080'
                    }]
                },
                tooltip: {
                    formatter: function() {
                        var s = '';
                          $.each(this.points, function(i, point) {
                              s += '<b>'+ this.series.name +'</b><br/>'+
                              Highcharts.dateFormat('%H:%M:%S %d-%m-%Y', this.x) +'<br/>'+
                              Highcharts.numberFormat(point.y, 2) +' mbps<br/>';
                          });
                        return s;

                    },
                    shared: true,
                },
                legend: {
                    enabled: false
                },
                exporting: {
                    enabled: false
                },
                plotOptions: {
                    areaspline: {
                        fillOpacity: 0.3
                    }
                },
                series: [{
                    name: 'Ingress Bandwidth',
                    data: (function() {
                        var data = [],
                            time = (new Date()).getTime(),
                            i;

                        for (i = -19; i <= 0; i++) {
                            data.push({
                                x: time + i * $chartloop,
                                y: null,
                            });
                        }
                        return data;
                    })()
                    }, {
                    name: 'Egress Bandwidth',
                    data: (function() {
                        var data = [],
                            time = (new Date()).getTime(),
                            i;

                        for (i = -19; i <= 0; i++) {
                            data.push({
                                x: time + i * $chartloop,
                                y: null,
                            });
                        }
                        return data;
                    })()
                }]
            });
        });
    });
    </script>
    </head>
    <body>
    <script src=\"http://code.highcharts.com/highcharts.js\"></script>
    <script src=\"http://code.highcharts.com/modules/exporting.js\"></script>
    <div id=\"container\" style=\"min-width: 310px; min-height: 700px; margin: 0 auto\"></div>";
    foot();
}

// Get Interface Enumeration number then generate expanded menu
elseif (empty($snmp) && !empty($ip) && !empty($community)) {
    $enumint = snmpget($ip, $community, "1.3.6.1.2.1.2.1.0");
    head();
    menu();
    foot();
}

// Return only Data values if snmp = true
elseif ( $snmp == "true" ) {
    if (!empty($oid0)) {
        echo $output0;
    }
    if (!empty($oid1)) {
        echo $output1;
    }
    return;
}

// Generate splash page if no data received from GET
else {
    head();
    menu();
    foot();
}
?>
