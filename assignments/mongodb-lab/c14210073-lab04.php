<?php

require_once 'vendor/autoload.php';

$client = new MongoDB\Client('mongodb://localhost:27017');
$resto = $client->restaurant_db->restaurants;

if(isset($_POST['filter'])){
    $criteria = [];
    if($_POST['filter'] == null){
        $cursor = $resto->find(
        $criteria,
        [
            'projection' => [
            '_id' => 0,
            ]
        ]);
    }else{
        $borough = [];
        $score = "";
        $cuisine = "";
        if(isset($_POST['filter']['borough'])) $borough = $_POST['filter']['borough'];
        if(isset($_POST['filter']['score'])) $score = $_POST['filter']['score'];
        if(isset($_POST['filter']['cuisine'])) $cuisine = $_POST['filter']['cuisine'];
        if($borough != '' && $borough != null){
            $or = [];
            foreach($borough as $bor){
                $or['$or'][] = ['borough' => $bor];
            }
            $criteria = $or;
        }
        if($score != '' && $score != null){
            if($criteria != []){
                $temp = $criteria;
                $criteria = [];
                $criteria['$and'][] = $temp;
                $criteria['$and'][] = ['grades.0.score' => ['$lt' => intval($score)]];
            }else{
                $criteria = ['grades.0.score' => ['$lt' => intval($score)]];
            }
        }
        if($cuisine != '' && $cuisine != null){
            if($criteria != []){
                if(!isset($criteria['$and'])){
                    $temp = $criteria;
                    $criteria = [];
                    $criteria['$and'][] = $temp;
                    $criteria['$and'][] = ['cuisine' => ['$regex' => $cuisine, '$options' => 'i']];
                }else{
                    $criteria['$and'][] = ['cuisine' => ['$regex' => $cuisine, '$options' => 'i']];
                }
            }else{
                $criteria = ['cuisine' => ['$regex' => $cuisine, '$options' => 'i']];
            }
        }
        $cursor = $resto->find(
            $criteria,
            [
                'projection' => [
                '_id' => 0,
                ]
            ]
        );
    }
    $data = [];
    foreach($cursor as $doc){
        $temp = [];
        foreach($doc as $key => $value){
            if($key === 'address'){
                $add = [];
                foreach($value as $k =>$v){
                    if($k === 'coord') continue;
                    $add[$k] = $v;
                }
                $temp[2] = $add['building'].', '.$add['street'];
            }else if($key === 'grades'){
                // Only display the lowest grade
                $temp[5] = $value[0]['score'].'/'.$value[0]['grade'];
            }else if($key === 'name'){
                $temp[1] = $value;
            }else if($key === 'cuisine'){
                $temp[4] = $value;
            }else if($key === 'borough'){
                $temp[3] = $value;    
            }else if($key === 'restaurant_id'){
                $temp[0] = $value;
            }
        }
        $data[] = [$temp[0],$temp[1],$temp[2],$temp[3],$temp[4],$temp[5]];
    }
    $headers = ['id','name','address','borough','cuisine','last grades'];
    echo json_encode(['data' => $data,'header' => $headers,'criteria' => $criteria]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- CDN for jquery -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
        <!-- CDN for Tailwind -->
        <script src="https://cdn.tailwindcss.com/3.3.0"></script>
        <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
        <!-- CDN for Tailwind Element -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tw-elements/dist/css/tw-elements.min.css">
        <!-- CDN for SweetAlert -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link
            href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700,900&display=swap"
            rel="stylesheet" />

        <style>
        @import url('https://fonts.googleapis.com/css2?family=Spinnaker&display=swap');
        *{font-family : 'Spinnaker',sans-serif !important}
        </style>


    </head>
    <body class="w-screen min-h-screen h-full pb-16 bg-slate-300 pt-16 justify-center items-center overflow-x-hidden overflow-y-auto">
        <div class="flex flex-col w-3/4 mx-auto">
            <div class="text-center mb-5">
                <h2 class="text-7xl text-white font-bold">MongoDB - PDDS - LAB 04</h2>
                <h4 class="text-4xl text-white">Christopher Julius</h4>
                <h4 class="text-4xl text-white">C14210073</h4>
            </div>  
        </div>
        <div class="w-3/4 shadow-2xl bg-white rounded-xl mx-auto py-8 pb-5">
            <div class="px-8 justify-center items-center">
                <div class="mb-3 mx-auto w-3/4">
                    <div class="relative mb-4 flex w-full flex-wrap items-stretch">
                        <div class="w-1/3 justify-center items-center pt-1">
                            <label for="borough" class="mr-8">Filter Borough : </label>
                        </div>
                        <select data-te-select-init multiple class="w-full" id="borough" name="borough">
                            <?php 
                                $bor = $resto->distinct('borough');
                                foreach($bor as $b){
                                    echo "<option value='".$b."'>".$b."</option>";
                                }
                            ?>
                        </select>
                        <label data-te-select-label-ref class="w-full">Borough</label>
                    </div>
                </div>
                <div class="mb-3 mx-auto w-3/4">
                    <div class="relative mb-4 flex w-full flex-wrap items-stretch">
                        <div class="w-1/3 justify-center items-center pt-1">
                            <label for="cuisine" class="mr-8">Filter Cuisine : </label>
                        </div>
                        <input
                            id="cuisine"
                            name="cuisine"
                            type="search"
                            class="relative m-0 -mr-0.5 block w-[1px] min-w-40 flex-auto rounded border border-solid border-neutral-300 bg-transparent bg-clip-padding px-3 py-[0.25rem] text-base font-normal leading-[1.6] text-neutral-700 outline-none transition duration-200 ease-in-out focus:z-[3] focus:border-primary focus:text-neutral-700 focus:shadow-[inset_0_0_0_1px_rgb(59,113,202)] focus:outline-none dark:border-neutral-600 dark:text-neutral-200 dark:placeholder:text-neutral-200 dark:focus:border-primary"
                            placeholder="Search"
                            aria-label="Search"
                            aria-describedby="button-addon1" />
                    </div>
                </div>
                <div class="mb-3 mx-auto w-3/4">
                    <div class="relative mb-4 flex w-full flex-wrap items-stretch justify-center items-center">
                        <div class="w-1/3 justify-center items-center pt-1">
                            <label for="score" class="mr-8">Filter Grade (less than) : </label>
                        </div>
                        <input
                            id="score"
                            name="score"
                            type="number"
                            class="relative m-0 -mr-0.5 block w-[1px] min-w-40 flex-auto rounded border border-solid border-neutral-300 bg-transparent bg-clip-padding px-3 py-[0.25rem] text-base font-normal leading-[1.6] text-neutral-700 outline-none transition duration-200 ease-in-out focus:z-[3] focus:border-primary focus:text-neutral-700 focus:shadow-[inset_0_0_0_1px_rgb(59,113,202)] focus:outline-none dark:border-neutral-600 dark:text-neutral-200 dark:placeholder:text-neutral-200 dark:focus:border-primary"
                            placeholder="Search"
                            aria-label="Search"
                            aria-describedby="button-addon1" />
                    </div>
                </div>
                <div id="datatable" data-te-max-height="460" data-te-fixed-header="true" data-te-width="300" data-te-clickable-rows= "true"></div>
            </div>
        </div>
    </body>
    <script src="https://cdn.jsdelivr.net/npm/tw-elements/dist/js/tw-elements.umd.min.js"></script>
    <script>
        $(document).ready(function(){
            let instance;
            function ajaxCall(filt = null){
                $.ajax({
                    method : 'POST',
                    data : {
                        filter : filt
                    },
                    success : function(response){
                        let res = JSON.parse(response);
                        console.log(res);
                        let data = {
                            columns: res.header,
                            rows: res.data,
                        };
                        if(!instance){
                            instance = new te.Datatable(document.getElementById('datatable'), data)
                        }else{
                            instance.update(data);
                        }
                        // console.log(instance);
                    }
                });
            }
            ajaxCall(null);
            $("#cuisine").on('change',function(){
                let cuisine = $("#cuisine").val();
                let borough = $("#borough").val();
                let score = $("#score").val();
                // if(instance) instance.destroy();
                ajaxCall({'cuisine':cuisine,'borough':borough,'score':score});
            })
            $("#borough").on('change',function(){
                let cuisine = $("#cuisine").val();
                let borough = $("#borough").val();
                let score = $("#score").val();
                // if(instance) instance.destroy();
                ajaxCall({'cuisine':cuisine,'borough':borough,'score':score});
            })
            $("#score").on('change',function(){
                let cuisine = $("#cuisine").val();
                let borough = $("#borough").val();
                let score = $("#score").val();
                // if(instance) instance.destroy();
                ajaxCall({'cuisine':cuisine,'borough':borough,'score':score});
            })
        });
    </script>
</html>