<?php
// Builds a balanced states view of the world.
include('con.php');

print 'BEGIN' . PHP_EOL;
$output = getcwd() . '/images/locations/';
if( ! is_dir( $output ) ) {
    mkdir( $output, 0755, true);
}

$query_raw = mysqli_query( $con,
    "
        SELECT
        *
        FROM wp_dt_location_grid lg1
        WHERE lg1.level = 0
          AND lg1.grid_id NOT IN ( SELECT lg11.admin0_grid_id FROM wp_dt_location_grid lg11 WHERE lg11.level = 1 AND lg11.admin0_grid_id = lg1.grid_id )
          AND lg1.admin0_grid_id NOT IN (100050711,100219347,100089589,100074576,100259978,100018514)
        UNION ALL
        SELECT
            *
        FROM wp_dt_location_grid lg2
        WHERE lg2.level = 1
          AND lg2.admin0_grid_id NOT IN (100050711,100219347,100089589,100074576,100259978,100018514)
        UNION ALL
        SELECT
            *
        FROM wp_dt_location_grid lg3
        WHERE lg3.level = 2
          AND lg3.admin0_grid_id IN (100050711,100219347,100089589,100074576,100259978,100018514)
        # (4770)
            " );
if ( empty( $query_raw ) ) {
    print_r( $con );
    die();
}
$query = mysqli_fetch_all( $query_raw, MYSQLI_ASSOC );

$current_files = [];
$scan = scandir( $output );
foreach( $scan as $file ) {
    if ( preg_match( '/.geojson/', $file ) ) {
        $current_files[] = $file;
    }
}

foreach( $query as $i => $row ) {
    $grid_id = $row['grid_id'];
    if ( in_array( $grid_id, $current_files ) ) {
        continue;
    }

    $query_inner_raw = mysqli_query( $con,
        "
        SELECT
              g.grid_id as id,
              g.grid_id,
              g.alt_name as name,
              g.alt_population as population,
              g.latitude,
              g.longitude,
              g.country_code,
              g.admin0_code,
              g.parent_id,
              g.admin0_grid_id,
              gc.alt_name as admin0_name,
              g.admin1_grid_id,
              ga1.alt_name as admin1_name,
              g.admin2_grid_id,
              ga2.alt_name as admin2_name,
              g.admin3_grid_id,
              ga3.alt_name as admin3_name,
              g.admin4_grid_id,
              ga4.alt_name as admin4_name,
              g.admin5_grid_id,
              ga5.alt_name as admin5_name,
              g.level,
              g.level_name,
              g.is_custom_location,
              g.north_latitude,
              g.south_latitude,
              g.east_longitude,
              g.west_longitude,
              gc.north_latitude as c_north_latitude,
              gc.south_latitude as c_south_latitude,
              gc.east_longitude as c_east_longitude,
              gc.west_longitude as c_west_longitude
            FROM wp_dt_location_grid as g
            LEFT JOIN wp_dt_location_grid as gc ON g.admin0_grid_id=gc.grid_id
            LEFT JOIN wp_dt_location_grid as ga1 ON g.admin1_grid_id=ga1.grid_id
            LEFT JOIN wp_dt_location_grid as ga2 ON g.admin2_grid_id=ga2.grid_id
            LEFT JOIN wp_dt_location_grid as ga3 ON g.admin3_grid_id=ga3.grid_id
            LEFT JOIN wp_dt_location_grid as ga4 ON g.admin4_grid_id=ga4.grid_id
            LEFT JOIN wp_dt_location_grid as ga5 ON g.admin5_grid_id=ga5.grid_id
            WHERE g.grid_id = $grid_id
            " );
    if ( empty( $query_inner_raw ) ) {
        print_r( $con );
        die();
    }
    $query_inner = mysqli_fetch_assoc( $query_inner_raw );

    $geojson = urlencode( file_get_contents('https://storage.googleapis.com/location-grid-mirror/low/'.$grid_id.'.geojson') );
    if ( 8192 < strlen($geojson) ) {
        print(strlen($geojson) . PHP_EOL);
    }

    continue;

    shell_exec('curl -g "https://api.mapbox.com/styles/v1/mapbox/light-v10/static/geojson('.$geojson.')/['.(float) $query_inner['c_west_longitude'].','.(float) $query_inner['c_south_latitude'].','.(float) $query_inner['c_east_longitude'].','.(float) $query_inner['c_north_latitude'].']/600x425@2x?padding=10,10,35&access_token=pk.eyJ1IjoiY2hyaXNjaGFzbSIsImEiOiJjajZyc2poNmEwZTdqMnFuenB0ODI5dWduIn0.6wKrDTf2exQJY-MY7Q1kRQ" --output images/locations/'.$query_inner['grid_id'].'.png');

    $im = imagecreatefrompng($output.$query_inner['grid_id'].'.png');
    $im2 = imagecrop($im, ['x' => 0, 'y' => 0, 'width' => '1200', 'height' => '800']);
    if ($im2 !== FALSE) {
        unlink($output.$query_inner['grid_id'].'.png');
        imagepng($im2, $output . $grid_id.'.png');
        imagedestroy($im2);
        print '*';
    } else {
        print( PHP_EOL . $grid_id . ' not cropped' . PHP_EOL );
    }
    imagedestroy($im);
}