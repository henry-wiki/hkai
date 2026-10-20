<?PHP

function get_diff_days($startDate,$endDate)
{
	// Convert dates to timestamps
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);

    // Calculate difference in seconds
    $differenceInSeconds = $endTimestamp - $startTimestamp;

    // Convert seconds to days
    return floor($differenceInSeconds / (60 * 60 * 24));

}
