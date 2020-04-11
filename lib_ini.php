<?php

function arr2ini(array $a, array $parent = array())
{
	$out = '';
	foreach ($a as $k => $v)
	{
		if (is_array($v))
		{
			//subsection case
			//merge all the sections into one array...
			$sec = array_merge((array) $parent, (array) $k);
			//add section information to the output
			$out .= '[' . join('.', $sec) . ']' . PHP_EOL;
			//recursively traverse deeper
			$out .= arr2ini($v, $sec);
		}
		else
		{
			//plain key->value case
			$out .= "$k=$v" . PHP_EOL;
		}
	}
	return $out;
}

function array_to_ini($array,$out="")
{
    $t="";
    $q=true;
    foreach($array as $c=>$d)
    {
        if(is_array($d))$t.=array_to_ini($d,$c);
        else
        {
            if($c===intval($c))
            {
                if(!empty($out))
                {
                    $t.="\r\n".$out." = \"".$d."\"";
                    //if($q!=2)$q=true;
                }
                else $t.="\r\n".$d;
            }
            else
            {
                $t.="\r\n".$c." = \"".$d."\"";
                //$q=2;
            }
        }
    }
    if($q!=true && !empty($out)) return "[".$out."]\r\n".$t;
    if(!empty($out)) return  $t;
    return trim($t);
}

function save_ini_file($array,$file)
{
	//$a=array_to_ini($array);
	$a=arr2ini($array);
    $ffl=fopen($file,"w");
    fwrite($ffl,$a);
    fclose($ffl);
}