<?php
require_once('set_db.php');
require_once('MyClass.php');

echo "START PARS";
$institutions = $pdo->query("SELECT * FROM `partner` ORDER BY `ID` ASC ")->fetchAll();//insert
//$institutions = $pdo->query("SELECT * FROM `school_coordinates` WHERE status=0 ")->fetchAll();//update
$objPars = new MyClass();




foreach ($institutions as $institution) {
        $dataFromSearch = ['title'=>$institution['title'],'City'=>$institution['City'], 'State'=>$institution['State'],'Latitude'=>$institution['Latitude'],'Longitude'=>$institution['Longitude']];//insert
//    $partner = $pdo->query("SELECT * FROM `partner` WHERE `ID` =".$institution['school_id']." ORDER BY `ID` DESC")->fetchAll();//update
//    $dataFromSearch = ['title'=>$partner[0]['title'],'City'=>$partner[0]['City'], 'State'=>$partner[0]['State'],'Latitude'=>$partner[0]['Latitude'],'Longitude'=>$partner[0]['Longitude']];//update
    $data = $objPars->getDataSchoolFromSearch($dataFromSearch);
//    var_dump($data);die;
    $code = $objPars->getCodeCoordinate($data['id']);
    $arrCoordinate = $objPars->decode($code);
    $coordinates = $objPars->parsArrPoint($arrCoordinate);

//    var_dump($partner[0]['title']);
//    var_dump($data['id']);
//    var_dump($code);
//    var_dump($coordinates);
//    var_dump($partner[0]['ID']);

//    die;


    if($code && $coordinates && $code != "Error") {
        $status = 1;

////update start///
//        $id = $institution['id'];
//        var_dump($id);
//        var_dump($code);
//        var_dump($coordinates);
//
//        $b=$pdo->prepare("UPDATE `school_coordinates` SET `code`='".$code."',`coordinates`='".$coordinates."',`Latitude`='".$data['latitude']."',`Longitude`='".$data['longitude']."',`status` = '1' WHERE `school_coordinates`.`id` =".$id.";");
//        $b->execute();
////update end

//insert start
        $pdo->exec("INSERT INTO school_coordinates (school_id, code, coordinates, Latitude, Longitude, status)

	 	   VALUES ('{$institution['ID']}', '{$code}', '{$coordinates}','{$data['latitude']}','{$data['longitude']}','{$status}')");
        $res = "good";
//insert end
        var_dump($institution['ID']);
        var_dump($res);
    }
    else{
        $status = 0;
        $pdo->exec("INSERT INTO school_coordinates (school_id, status)

	 	   VALUES ('{$institution['ID']}', '{$status}')");
        $res = "error";
        var_dump($institution['ID']);
        var_dump($res);
    }
}


echo "END PARS";



?>