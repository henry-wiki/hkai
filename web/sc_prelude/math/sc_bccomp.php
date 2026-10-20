<?php

function sc_bccomp($left, $right, $scale = null)
{
    return bccomp(sc_clean_number($left), sc_clean_number($right), $scale);
}
