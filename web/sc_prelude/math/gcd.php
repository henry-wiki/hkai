<?PHP

function gcd($n, $m) //greatest common divisor
{
    $n=abs($n);
	$m=abs($m);
    if ($n==0 && $m==0)
        return 1; //avoid infinite recursion
    if ($n==$m && $n>=1)
        return $n;

    return $m < $n ? gcd($n-$m,$n) : gcd($n,$m-$n);
}
