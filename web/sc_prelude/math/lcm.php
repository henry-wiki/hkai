<?PHP

function lcm($n, $m) //least common multiple
{
    return $m * ( $n/ gcd($n,$m) );
}
