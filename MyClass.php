<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 4/9/20
 * Time: 3:19 PM
 */
require_once('set_db.php');
include_once ('curl.php');

class MyClass
{

    protected static $precision = 5;
    const EARTH_RADIUS = 6372795;

    /**
     * Apply Google Polyline algorithm to list of points.
     *
     * @param array $points List of points to encode. Can be a list of tuples,
     *                      or a flat, one-dimensional array.
     *
     * @return string encoded string
     */
    final public static function encode( $points )
    {
        $points = self::flatten($points);
        $encodedString = '';
        $index = 0;
        $previous = array(0,0);
        foreach ( $points as $number ) {
            $number = (float)($number);
            $number = (int)round($number * pow(10, static::$precision));
            $diff = $number - $previous[$index % 2];
            $previous[$index % 2] = $number;
            $number = $diff;
            $index++;
            $number = ($number < 0) ? ~($number << 1) : ($number << 1);
            $chunk = '';
            while ( $number >= 0x20 ) {
                $chunk .= chr((0x20 | ($number & 0x1f)) + 63);
                $number >>= 5;
            }
            $chunk .= chr($number + 63);
            $encodedString .= $chunk;
        }
        return $encodedString;
    }

    /**
     * Reverse Google Polyline algorithm on encoded string.
     *
     * @param string $string Encoded string to extract points from.
     *
     * @return array points
     */
    final public static function decode( $string )
    {
        $points = array();
        $index = $i = 0;
        $previous = array(0,0);
        while ($i < strlen($string)) {
            $shift = $result = 0x00;
            do {
                $bit = ord(substr($string, $i++)) - 63;
                $result |= ($bit & 0x1f) << $shift;
                $shift += 5;
            } while ($bit >= 0x20);

            $diff = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $number = $previous[$index % 2] + $diff;
            $previous[$index % 2] = $number;
            $index++;
            $points[] = $number * 1 / pow(10, static::$precision);
        }
        return $points;
    }

    /**
     * Reduce multi-dimensional to single list
     *
     * @param array $array Subject array to flatten.
     *
     * @return array flattened
     */
    final public static function flatten( $array )
    {
        $flatten = array();
        array_walk_recursive(
            $array, // @codeCoverageIgnore
            function ($current) use (&$flatten) {
                $flatten[] = $current;
            }
        );
        return $flatten;
    }

    /**
     * Concat list into pairs of points
     *
     * @param array $list One-dimensional array to segment into list of tuples.
     *
     * @return array pairs
     */
    final public static function pair( $list )
    {
        return is_array($list) ? array_chunk($list, 2) : array();
    }

    public function getDataSchoolFromSearch($arrSearchData)
    {
//        var_dump($arrSearchData);die;
        $search = $arrSearchData['title'];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.apartments.com/services/geography/search/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS =>"{\"t\":\"".$search."\",\"l\":[-76.62,39.285]}",
            CURLOPT_HTTPHEADER => array(
                ": authority: www.apartments.com",
                ": method: POST",
                ": path: /services/geography/search/",
                ": scheme: https",
                "accept:  application/json, text/javascript, */*; q=0.01",
                "accept-encoding:  gzip, deflate, br",
                "accept-language:  ru,en-US;q=0.9,en;q=0.8",
                "cache-control:  no-cache",
                "content-type:  application/json",
                "origin:  https://www.apartments.com",
                "pragma:  no-cache",
                "referer:  https://www.apartments.com/",
                "sec-fetch-dest:  empty",
                "sec-fetch-mode:  cors",
                "sec-fetch-site:  same-origin",
                "user-agent:  Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36",
                "x-requested-with:  XMLHttpRequest"
            ),
        ));

        $response = curl_exec($curl);
//        echo $response;
//        $info = curl_getinfo($curl);
//        var_dump($info);
        curl_close($curl);



        $arrSchoolData = explode(",{\"ID\"",$response);
//        var_dump($arrSchoolData);
        preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $response, $id);
//        var_dump($id);

        $result = $this->filterByPoint($arrSchoolData,$arrSearchData);

        if(!$result['id'] || !$result['latitude'] || !$result['longitude']){
            $result = $this->filterByDbData($arrSchoolData,$arrSearchData);
            return $result;
        }else{
            return $result;
        }

    }

    public function filterByDbData($arrSchoolData,$arrSearchData)
    {
   //если не указана широта и долгота
        $status =1;
        foreach ($arrSchoolData as $key => $value){
            if($arrSearchData['City']){
                if(stristr($value, $arrSearchData['City']) == TRUE){
                    preg_match_all("/(?<=\"Location\":)([\s\S]+?)(?=})/", $value, $location);
                    preg_match_all("/(?<=\"Latitude\":)([\s\S]+?)(?=,\"Longitude\")/", $location[0][0], $latitude);
                    preg_match_all("/(?<=\"Longitude\":)([\s\S]+?)(?=$)/", $location[0][0], $longitude);
                    preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $value, $id);
                    $arrResult =['id'=>$id[0][0],'latitude'=>$latitude[0][0],'longitude'=>$longitude[0][0]];

                    $code = $this->getCodeCoordinate($id[0][0]);
                    if($code && $code!='Error'){
                        return $arrResult;
                    }
                }
            }else{
                $status = 0;
            }
            if($arrSearchData['State']){

                if(stristr($value, $arrSearchData['State']) == TRUE){
                    preg_match_all("/(?<=\"Location\":)([\s\S]+?)(?=})/", $value, $location);
                    preg_match_all("/(?<=\"Latitude\":)([\s\S]+?)(?=,\"Longitude\")/", $location[0][0], $latitude);
                    preg_match_all("/(?<=\"Longitude\":)([\s\S]+?)(?=$)/", $location[0][0], $longitude);
                    preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $value, $id);
                    $arrResult =['id'=>$id[0][0],'latitude'=>$latitude[0][0],'longitude'=>$longitude[0][0]];

                    $code = $this->getCodeCoordinate($id[0][0]);
                    if($code && $code!='Error'){
                        return $arrResult;
                    }
                }
            }else{
                $status = 0;
            }
        }

        if($status == 0){
            preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $arrSchoolData[0], $id);
            return $id[0][0];
        }
    }



    public function filterByPoint($arrSchoolData,$arrSearchData)
    {
        if(count($arrSchoolData) > 1){
            if($arrSearchData['Latitude'] && $arrSearchData['Longitude']){
//                echo '<h2>Location</h2>';
                $arrPoints = [];
                $arrDist = [];
                //поиск ближайшей точки
                foreach ($arrSchoolData as $key => $value){
//            var_dump($arrSchoolData);
//            if($value){
//                if($arrSearchData['City']){
//                    if(stristr($value, $arrSearchData['City']) == TRUE){
//                        preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $value, $id);
//                        $code = $this->getCodeCoordinate($id[0][0]);
//                        if($code && $code!='Error'){
//                            return $id[0][0];
//                        }
//                    }
//                }
//                if($arrSearchData['State']){
//
//                    if(stristr($value, $arrSearchData['State']) == TRUE){
//                        preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $value, $id);
//                        $code = $this->getCodeCoordinate($id[0][0]);
//                        if($code && $code!='Error'){
//                            return $id[0][0];
//                        }
//                    }
//                }
//                if( $arrSearchData['State'] && stristr($value, $arrSearchData['State']) == FALSE && $arrSearchData['City'] && stristr($value, $arrSearchData['City']) == FALSE){
//                    preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $value, $id);
//                    return $id[0][0];
//                }
//            }
                    preg_match_all("/(?<=\"Location\":)([\s\S]+?)(?=})/", $value, $location);
//                   var_dump($location[0][0]);
                    preg_match_all("/(?<=\"Latitude\":)([\s\S]+?)(?=,\"Longitude\")/", $location[0][0], $latitude);
                    preg_match_all("/(?<=\"Longitude\":)([\s\S]+?)(?=$)/", $location[0][0], $longitude);


                    array_push($arrDist,$this->calculateTheDistance($latitude[0][0],$longitude[0][0],$arrSearchData['Latitude'], $arrSearchData['Longitude']));
                    $arrPoints[] = ['latitude'=>$latitude[0][0],'longitude'=>$longitude[0][0]];

                }
//               echo '<h1>arr</h1>';
//               var_dump($arrDist);

                $minDistKey = array_keys($arrDist, min($arrDist));
//                var_dump($minDistKey);

                $suitablePoint = $arrSchoolData[$minDistKey[0]];
//                var_dump($suitablePoint);
                preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $suitablePoint, $id);
                preg_match_all("/(?<=\"Location\":)([\s\S]+?)(?=})/", $suitablePoint, $location);
//           var_dump($location[0][0]);
                preg_match_all("/(?<=\"Latitude\":)([\s\S]+?)(?=,\"Longitude\")/", $location[0][0], $latitude);
                preg_match_all("/(?<=\"Longitude\":)([\s\S]+?)(?=$)/", $location[0][0], $longitude);

                $arrResult =['id'=>$id[0][0],'latitude'=>$latitude[0][0],'longitude'=>$longitude[0][0]];
//               var_dump($arrResult);

                return $arrResult;
            }

        }else{
            preg_match_all("/(?<=\"Location\":)([\s\S]+?)(?=})/", $response, $location);
            preg_match_all("/(?<=\"Latitude\":)([\s\S]+?)(?=,\"Longitude\")/", $location[0][0], $latitude);
            preg_match_all("/(?<=\"Longitude\":)([\s\S]+?)(?=$)/", $location[0][0], $longitude);
            preg_match_all("/(?<=\"v\":)([\s\S]+?)(?=})/", $response, $id);
            $arrResult =['id'=>$id[0][0],'latitude'=>$latitude[0][0],'longitude'=>$longitude[0][0]];
            return $arrResult;
        }

    }

    /*
    * Расстояние между двумя точками
    * $φA, $λA - широта, долгота 1-й точки,
    * $φB, $λB - широта, долгота 2-й точки
    */
    public function calculateTheDistance ($φA, $λA, $φB, $λB) {

// перевести координаты в радианы
        $lat1 = $φA * M_PI / 180;
        $lat2 = $φB * M_PI / 180;
        $long1 = $λA * M_PI / 180;
        $long2 = $λB * M_PI / 180;

// косинусы и синусы широт и разницы долгот
        $cl1 = cos($lat1);
        $cl2 = cos($lat2);
        $sl1 = sin($lat1);
        $sl2 = sin($lat2);
        $delta = $long2 - $long1;
        $cdelta = cos($delta);
        $sdelta = sin($delta);

// вычисления длины большого круга
        $y = sqrt(pow($cl2 * $sdelta, 2) + pow($cl1 * $sl2 - $sl1 * $cl2 * $cdelta, 2));
        $x = $sl1 * $sl2 + $cl1 * $cl2 * $cdelta;

//
        $ad = atan2($y, $x);
        $dist = $ad * self::EARTH_RADIUS;

        return $dist;
    }

    public function getCodeCoordinate($idSchool)
    {
        $url = "https://shapes.apartments.com/shapes/college/".$idSchool."/high/";
        $data = $this->getSiteData($url);
        preg_match_all("/(?<={\"lines\":\[\[\")([\s\S]+?)(?=\"]]})/", $data, $code);

        if ($code[0]){
            return $code[0][0];
        }else{
            return 'Error';
        }


    }

    public function parsArrPoint($arrPoint)
    {
        $strCoordinate = "{\"0\":[";

        $i = 1;
        $arr = [];
        foreach ($arrPoint as $point)
        {

            if($i % 2 === 0){
                $arr['longitude'] = $point;
                $strCoordinate .= "[".$arr['longitude'].",".$arr ['latitude']."],";
                $arr = [];
            }else{
                $arr ['latitude']= $point;
            }


            $i++;
//            if($arr ['latitude'] && $arr['longitude']){
//                $strCoordinate .= "[".$arr['longitude'].",".$arr ['latitude']."]";
//
//            }
        }
//        var_dump($strCoordinate);
        $strCoordinate .= "]}";
        $strCoordinate = str_replace(",]}","]}", $strCoordinate);
//        echo $strCoordinate;
//        die;
        return $strCoordinate;
    }

    /**
     * @param $url
     * @return mixed
     */
    public function getSiteData($url)
    {
        $data = curl_get($url);
        return $data;
    }
    public function StartPars($title)
    {

            $idFromSearch = $this->getIdSchoolFromSearch($title);
            $code = $this->getCodeCoordinate($idFromSearch);
//            var_dump($code);die;
            $arrCoordinate = $this->decode($code);
//            var_dump($arrCoordinate);die;
            $strCoordinate = $this->parsArrPoint($arrCoordinate);

            return $strCoordinate;
//            var_dump($strCoordinate);die;
//
//            if($code && $strCoordinate){
//                $coordinateSave = new SchoolCoordinates();
//                $coordinateSave->school_id = $value['ID'];
//                $coordinateSave->code =  $code;
//                $coordinateSave->coordinates = $strCoordinate;
//                $coordinateSave->status = 1;
//                $coordinateSave->save();
//
//                $status = "good";
//                var_dump($value['ID']);
//                var_dump($status);
//            }else{
//                $coordinateSave = new SchoolCoordinates();
//                $coordinateSave->school_id = $value['ID'];
//                $coordinateSave->status = 0;
//                $coordinateSave->save();
//                $status = "error";
//                var_dump($value['ID']);
//                var_dump($status);
//            }


    }

}