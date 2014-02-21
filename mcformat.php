<?php

// Multibyte str_split()
function MC_str_split( $string )
{
    return preg_split('/(?<!^)(?!$)/u', $string );
}

// http://www.wiki.vg/Chat
function MC_parseMotdColors($motd)
{
    $inColorSequence = false;
    $openSpan = false;
    $coloredMotd = '';

    foreach (MC_str_split($motd) as $character) {
        if ($inColorSequence) {

            // find color and insert span
            switch ($character) {
            case '0':
                $color = '#000000';
                break;
            case '1':
                $color = '#0000aa';
                break;
            case '2':
                $color = '#00aa00';
                break;
            case '3':
                $color = '#00aaaa';
                break;
            case '4':
                $color = '#aa0000';
                break;
            case '5':
                $color = '#aa00aa';
                break;
            case '6':
                $color = '#ffaa00';
                break;
            case '7':
                $color = '#aaaaaa';
                break;
            case '8':
                $color = '#555555';
                break;
            case '9':
                $color = '#5555ff';
                break;
            case 'a':
                $color = '#55ff55';
                break;
            case 'b':
                $color = '#55ffff';
                break;
            case 'c':
                $color = '#ff5555';
                break;
            case 'd':
                $color = '#ff55ff';
                break;
            case 'e':
                $color = '#ffff55';
                break;
            case 'f':
            case 'r':
                $color = '#ffffff';
                break;
            default:
                $color = false;
                break;
            }

            if ($color) {
                if ($openSpan) {
                    $coloredMotd .= '</span>';
                }

                $coloredMotd .= '<span style="color:' . $color . ';">';
                $openSpan = true;
            }

            $inColorSequence = false;
        } elseif ($character== '§') {
            $inColorSequence = true;
        } else {
            $coloredMotd .= $character;
        }
    }

    if ($openSpan) {
        $coloredMotd .= '</span>';
    }

    return $coloredMotd;
}

?>