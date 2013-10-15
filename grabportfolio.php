<?php

// for index comparison, portfolio was mostly formed at market open 10/8/13
// note that comparison is slightly biased as index dividend yield is much higher
$DIA_INITIAL_PRICE = 149.31;
$SPY_INITIAL_PRICE = 167.42;
$QQQ_INITIAL_PRICE = 78.75;
$SDOG_INITIAL_PRICE = 31.90;

$CASH = -1450.51;

// ticker, shares, cost basis
$stocks = array 
    (
        array("CIMT", 756, 6.65),
        array("AMBA", 314, 19.45),
        array("GPRC", 3730, 2.40),
        array("CCCL", 1400, 3.70),
        array("PXLW", 1100, 5.02),
        array("GALE", 3015, 2.43),
        array("KGJI", 2700, 1.89),
        array("CAMP", 217, 23.04),
        array("GSAT", 2500, 1.25),
        array("SGOC", 1100, 2.95),
    );

setlocale(LC_MONETARY,"en_US");

function isMarketOpen() {
    // simplified--excludes holidays
    date_default_timezone_set('US/Eastern');
    $day = date('l');
    // weekend, closed
    if ($day == 'Saturday' || $day == 'Sunday') {
        return false;
    }
    // weekday before or after hours
    $hour = date('H');
    if ($hour < 9 || $hour >= 16) {
        return false;
    }
    // damn you for opening market at 9:30...
    if ($hour == 9 && date('i') < 30) {
        return false;
    }
    return true;
}

function getColoredString($string, $color = null) {
    $colored_string = "";

    if (isset($color)) {
        $colors = array();
        $colors['black'] = '0;30';
        $colors['light_gray'] = '0;37';
        $colors['red'] = '0;31';
        $colors['green'] = '0;32';
        $colors['blue'] = '0;34';
        $colors['purple'] = '0;35';
        $colors['cyan'] = '0;36';
        $colors['light_cyan'] = '1;36';
        // default to black
        $txtColor = !empty($colors[$color]) ? $colors[$color] : $colors['black'];
        $colored_string .= "\033[" . $txtColor . "m"; 
    }
    else {
        return $string;
    }

    // Add string and end coloring
    $colored_string .=  $string . "\033[0m";

    return $colored_string;
}

function monify_string($value, $paren = false) {
    if ($value >= 0) {
        $value= getColoredString("+" . $value, 'green');
    }
    else {
        $value = getColoredString($value, 'red');
    }
    if ($paren) {
        $value = '(' . $value . ')';
    }
    return $value;
}

function displayRow($data, $initialPrice = null) {
    $ticker = $data[0];
    $shares = $data[1];
    $costBasis = $data[2];

    $sourceURL = "http://finance.yahoo.com/d/quotes.csv?s=$ticker&f=b3b2c6l1p5rp6";
    $sourceData  = file_get_contents( $sourceURL );

    // separate into lines
    $sourceLines = str_getcsv($sourceData, "\n"); 

    foreach( $sourceLines as $line ) {
        $contents = str_getcsv( $line );
        // Now, is an array of the comma-separated contents of a line
        $bid = $contents[0];
        $ask = $contents[1];
        $change = $contents[2];
        $last = $contents[3];
        $ps = $contents[4];
        $pe = $contents[5];
        $pb = $contents[6];

        // if bid or ask are not available (or after hours), use last price
        if ( empty($bid) || empty($ask) || $bid <= 0 || $ask <= 0 || !isMarketOpen()) {
            $price = $last;
        }
        else {
            $price = ($bid+$ask)/2;
            // handle anomalies
            if ($price > 1.1*$last || $price < 0.9*$last) {
                $price = $last;
            }
        }

        $changePercent = monify_string(number_format(($change/$price)*100, 2)) . "%";
        $value = $price*$shares;
        $dayGain = $change*$shares;
        $totalGain = $value - $costBasis*$shares;
        if ($value > 0) {
            $valueString = "$" . money_format("%!n", $value);
            $dayGainString = monify_string(money_format("%!n",$dayGain), true);
            $totalGainString = monify_string(money_format("%!n", $totalGain), true);
            $totalChangePercent = monify_string(number_format(($totalGain/($costBasis*$shares))*100, 2)) . "%";
        }
        else {
            $valueString = '';
            $dayGainString = '';
            $totalGainString = '';
            if (!empty($initialPrice)) {
                // extra tab needed to properly align with table below
                $totalChangePercent = "\t" . monify_string(number_format((($price-$initialPrice)/($initialPrice))*100, 2)) . "%";
            }
        }

        // edge case to ensure columns align properly
        if ($dayGain > -10 && $dayGain < 10) {
            $dayGainString .= "\t";
        }

        echo "$ticker\t$price\t$changePercent\t$dayGainString\t$valueString\t$totalChangePercent\t$totalGainString\t$ps\t$pe\t$pb\n";
        return array($dayGain, $value);
    }
}

$marketOpen = '';
if (isMarketOpen()) {
    $marketOpen = getColoredString('Market Open', 'cyan');
}
else {
    $marketOpen = getColoredString('Market Closed', 'light_gray');
}
echo "\nComparison indexes (total from 10/8/13):\t\t\t$marketOpen\n";
$dia = array('DIA', 0, 0);
$spy = array('SPY', 0, 0);
$qqq = array('QQQ', 0, 0);
$sdog = array('SDOG', 0, 0);
displayRow($dia, $DIA_INITIAL_PRICE);
displayRow($spy, $SPY_INITIAL_PRICE);
displayRow($qqq, $QQQ_INITIAL_PRICE);
displayRow($sdog, $SDOG_INITIAL_PRICE);
echo "\n";

echo "Ticker\tPrice\tChange\t(Day Gain)\tValue\t\tTot %\t(Tot gain)\tP/S\tP/E\tP/B\n";

$dayGain = 0;
$total = 0;
$totalGain = 0;
foreach( $stocks as $stock ) {
    $vals = displayRow($stock);
    $dayGain += $vals[0];
    $total += $vals[1];
    $totalGain += $vals[1] - $stock[2]*$stock[1];
}
$total += $CASH;
$changePercent = monify_string(number_format(($dayGain/($total-$dayGain))*100, 2)) . "%";
$dayGainString = monify_string(money_format("%!n", $dayGain), true);
// edge case to ensure columns align properly
if ($dayGain > -10 && $dayGain < 10) {
    $dayGainString .= "\t";
}
$totalString = "$" . money_format("%!n", $total);
$totalChangePercent = monify_string(number_format(($totalGain/($total-$totalGain))*100, 2)) . "%";
$totalGainString = monify_string(money_format("%!n", $totalGain), true);
// cash
if ($CASH < 0) {
    $cashString = "-$" . money_format("%!n", abs($CASH));
}
else {
    $cashString = "$" . money_format("%!n", $CASH);
}
echo "\nCash:\t\t\t\t\t$cashString\n";
echo "Total:\t\t$changePercent\t$dayGainString\t$totalString\t$totalChangePercent\t$totalGainString\n\n";

?>