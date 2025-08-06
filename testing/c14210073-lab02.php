<?php
// Include Predis library
require_once 'vendor/autoload.php';
    
    use Predis\Client;
    $redis = new Client([
                    'name' => 'Redis Time Series Lab 02',
                    'scheme' =>'tcp',
                    'host' => '54.176.6.43',
                    'port' => 6379,
                    'timeout' => 5]);

    if(isset($_FILES['file'])){
        $path_info = pathinfo($_FILES['file']['name']);
        $extension = $path_info['extension'];
        if($extension != 'csv'){
            echo json_encode(['status' => '422', 'message' => 'Only Accept .CSV file']);
            exit;
        }
        else{
            $file_tmp = $_FILES['file']['tmp_name'];
            $handle = fopen($file_tmp, 'r');
            $label = $_FILES['file']['name'];
            $label_compacted = $label.'_compacted';
            if ($handle !== false) {
                // Read the first row to use as headers
                $headers = fgetcsv($handle, 0, ',');
                foreach($headers as $h){
                    if($h == 'dt') continue;
                    // deleting the existing Time Series
                    $redis->executeRaw(['DEL',$h]);
                    $redis->executeRaw(['DEL',$h.'_compacted']);
                    // Create new Time Series with the same Name
                    $redis->executeRaw(['TS.CREATE',$h,'LABELS','data',$label]);
                    // create the compaction
                    $redis->executeRaw(['TS.CREATE',$h.'_compacted','LABELS','data',$label_compacted]);
                    // create the rule
                    if(str_contains($h,'Average')){
                        // for average
                        $redis->executeRaw(['TS.CREATERULE',$h,$h.'_compacted','AGGREGATION','avg',31556952000]);
                    }else if(str_contains($h,'Max')){
                        // for maximum
                        $redis->executeRaw(['TS.CREATERULE',$h,$h.'_compacted','AGGREGATION','max',31556952000]);
                    }else if(str_contains($h,'Min')){
                        // for minimum
                        $redis->executeRaw(['TS.CREATERULE',$h,$h.'_compacted','AGGREGATION','min',31556952000]);
                    }else if(str_contains($h,'Sum')){
                        // for sum
                        $redis->executeRaw(['TS.CREATERULE',$h,$h.'_compacted','AGGREGATION','sum',31556952000]);
                    }else{
                        // default as average
                        $redis->executeRaw(['TS.CREATERULE',$h,$h.'_compacted','AGGREGATION','avg',31556952000]);
                    }
                }
                // Process the remaining rows
                while (($data = fgetcsv($handle, 0, ',')) !== false) {
                    // convert the first column to a timestamp with epoch time
                    list($month, $day, $year) = explode('/', $data[0]);
                    // manual convert to Timestamp
                    date_default_timezone_set("UTC");
                    $timestamp = strtotime($year.'-'.$month.'-'.$day.' 00:00:00')*1000;
                    // insert the data into TimeSeries
                    for($i=1;$i<count($data);$i++){
                        $redis->executeRaw(['TS.ADD',$headers[$i],$timestamp,$data[$i]]);
                    }
                }
                fclose($handle);
                // Process reading data from TimeSeries
                // default is all table
                $data = [];
                $dataCompact = [];
                $dataGraph = [];
                $dataCompactGraph = [];
                foreach($headers as $h){
                    if($h == 'dt') continue;
                    $temp = $redis->executeRaw(['TS.RANGE',$h,'-','+']);
                    for($i = 0; $i < count($temp); $i++){
                        $dataGraph[$h][] = [gmdate('d-M-Y',($temp[$i][0]/1000)),floatVal($temp[$i][1]->getPayload())];
                        if(!isset($data[$i])) $data[$i][] = gmdate('d-M-Y',($temp[$i][0]/1000));
                        $data[$i][] = floatval($temp[$i][1]->getPayload());
                    }
                    $tempCompact = $redis->executeRaw(['TS.RANGE',$h.'_compacted','-','+']);
                    for($j = 0;$j < count($tempCompact); $j++){
                        $dataCompactGraph[$h.'_compacted'][] = [gmdate('d-M-Y',($tempCompact[$j][0]/1000)),floatval($tempCompact[$j][1]->getPayload())];
                        if(!isset($dataCompact[$j])) $dataCompact[$j][] = gmdate('d-M-Y',($tempCompact[$j][0]/1000));
                        $dataCompact[$j][] = floatval($tempCompact[$j][1]->getPayload());
                    }
                };
                echo json_encode(['status' => '200' , 'message' => 'File Uploaded', 'data' => $data, 'data_compacted' => $dataCompact, 'headers' => $headers, 'data_graph' => $dataGraph, 'data_compacted_graph' => $dataCompactGraph]);
            } else {
                echo json_encode(['status' => '500', 'message' => 'Failed to read file']);
            }
            exit;
        }
    }
    if($_SERVER['REQUEST_METHOD'] !== 'GET'){
        echo json_encode(['status' => '405', 'message' => 'Only accept GET method']);
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
                <h2 class="text-7xl text-white font-bold">REDIS - PDDS - LAB 02</h2>
                <h4 class="text-4xl text-white">Christopher Julius</h4>
                <h4 class="text-4xl text-white">C14210073</h4>
            </div>  
        </div>
        <div class="w-1/2 shadow-2xl bg-white rounded-xl mx-auto py-8 pb-5 items-center justify-center mb-5">
            <div class="text-center text-2xl mb-3 font-bold">Upload File</div>
            <div class="mb-2 w-1/2 mx-auto">
                <input
                    class="relative m-0 block w-full min-w-0 flex-auto rounded border border-solid border-neutral-300 bg-clip-padding px-3 py-[0.32rem] text-base font-normal text-neutral-700 transition duration-300 ease-in-out file:-mx-3 file:-my-[0.32rem] file:overflow-hidden file:rounded-none file:border-0 file:border-solid file:border-inherit file:bg-neutral-100 file:px-3 file:py-[0.32rem] file:text-neutral-700 file:transition file:duration-150 file:ease-in-out file:[border-inline-end-width:1px] file:[margin-inline-end:0.75rem] hover:file:bg-neutral-200 focus:border-primary focus:text-neutral-700 focus:shadow-te-primary focus:outline-none dark:border-neutral-600 dark:text-neutral-200 dark:file:bg-neutral-700 dark:file:text-neutral-100 dark:focus:border-primary"
                    type="file"
                    accept=".csv"
                    id="upload" />
            </div>
        </div>
        <div class="mt-10 w-3/4 shadow-2xl bg-white rounded-xl mx-auto pb-5 pl-0 items-center justify-center mb-5 px-5">
            <div class="w-full mx-auto pl-8">
            <!--Tabs navigation-->
                <ul
                class="mb-5 flex list-none flex-row flex-wrap border-b-0px-5"
                role="tablist"
                data-te-nav-ref>
                    <li role="presentation" class="flex-grow basis-0 text-center">
                        <a
                        href="#tabs-full-table"
                        class="font-bold my-2 block border-x-0 border-b-2 border-t-0 border-transparent px-7 pb-3.5 pt-4 text-xs font-medium uppercase leading-tight text-neutral-500 hover:isolate hover:border-transparent hover:bg-neutral-100 focus:isolate focus:border-transparent data-[te-nav-active]:border-primary data-[te-nav-active]:text-primary dark:text-neutral-400 dark:hover:bg-transparent dark:data-[te-nav-active]:border-primary-400 dark:data-[te-nav-active]:text-primary-400"
                        data-te-toggle="pill"
                        data-te-target="#tabs-full-table"
                        data-te-nav-active
                        role="tab"
                        aria-controls="tabs-full-table"
                        aria-selected="true"
                        >Full Table</a>
                    </li>
                    <li role="presentation" class="flex-grow basis-0 text-center">
                        <a
                        href="#tabs-compact-table"
                        class="font-bold my-2 block border-x-0 border-b-2 border-t-0 border-transparent px-7 pb-3.5 pt-4 text-xs font-medium uppercase leading-tight text-neutral-500 hover:isolate hover:border-transparent hover:bg-neutral-100 focus:isolate focus:border-transparent data-[te-nav-active]:border-primary data-[te-nav-active]:text-primary dark:text-neutral-400 dark:hover:bg-transparent dark:data-[te-nav-active]:border-primary-400 dark:data-[te-nav-active]:text-primary-400"
                        data-te-toggle="pill"
                        data-te-target="#tabs-compact-table"
                        role="tab"
                        aria-controls="tabs-compact-table"
                        aria-selected="false"
                        >Compacted Table</a
                        >
                    </li>
                    <li role="presentation" class="flex-grow basis-0 text-center">
                        <a
                        href="#tabs-full-graph"
                        class="font-bold my-2 block border-x-0 border-b-2 border-t-0 border-transparent px-7 pb-3.5 pt-4 text-xs font-medium uppercase leading-tight text-neutral-500 hover:isolate hover:border-transparent hover:bg-neutral-100 focus:isolate focus:border-transparent data-[te-nav-active]:border-primary data-[te-nav-active]:text-primary dark:text-neutral-400 dark:hover:bg-transparent dark:data-[te-nav-active]:border-primary-400 dark:data-[te-nav-active]:text-primary-400"
                        data-te-toggle="pill"
                        data-te-target="#tabs-full-graph"
                        role="tab"
                        aria-controls="tabs-full-graph-tab"
                        aria-selected="false"
                        >Full graph</a
                        >
                    </li>
                    <li role="presentation" class="flex-grow basis-0 text-center">
                        <a
                        href="#tabs-compact-graph"
                        class="font-bold my-2 block border-x-0 border-b-2 border-t-0 border-transparent px-7 pb-3.5 pt-4 text-xs font-medium uppercase leading-tight text-neutral-500 hover:isolate hover:border-transparent hover:bg-neutral-100 focus:isolate focus:border-transparent data-[te-nav-active]:border-primary data-[te-nav-active]:text-primary dark:text-neutral-400 dark:hover:bg-transparent dark:data-[te-nav-active]:border-primary-400 dark:data-[te-nav-active]:text-primary-400"
                        data-te-toggle="pill"
                        data-te-target="#tabs-compact-graph"
                        role="tab"
                        aria-controls="tabs-compact-graph-tab"
                        aria-selected="false"
                        >compact graph</a
                        >
                    </li>
                </ul>
            </div>

            <!--Tabs content-->
            <div class="mb-6 min-w-full w-full">
                <div
                    class="hidden opacity-100 transition-opacity duration-150 ease-linear data-[te-tab-active]:block px-5 w-full"
                    id="tabs-full-table"
                    role="tabpanel"
                    aria-labelledby="tabs-full-table-tab"
                    data-te-tab-active>
                    Waiting for file to be uploaded
                </div>
                <div
                    class="hidden opacity-0 transition-opacity duration-150 ease-linear data-[te-tab-active]:block px-5"
                    id="tabs-compact-table"
                    role="tabpanel"
                    aria-labelledby="tabs-compact-table-tab"
                    >
                    Waiting for file to be uploaded
                </div>
                <div
                    class="hidden opacity-0 transition-opacity duration-150 ease-linear data-[te-tab-active]:block px-5"
                    id="tabs-full-graph"
                    role="tabpanel"
                    aria-labelledby="tabs-full-graph-tab">
                    Waiting for file to be uploaded
                </div>
                <div
                    class="hidden opacity-0 transition-opacity duration-150 ease-linear data-[te-tab-active]:block px-5"
                    id="tabs-compact-graph"
                    role="tabpanel"
                    aria-labelledby="tabs-compact-graph-tab">
                    Waiting for file to be uploaded
                </div>
            </div>
        </div>
    </body>
    <script src="https://cdn.jsdelivr.net/npm/tw-elements/dist/js/tw-elements.umd.min.js"></script>
    <script>
        
        $(document).ready(function(){
            $("#upload").on('change',function(){
                Swal.fire({
                    title: 'Uploading...',
                    html: 'Please wait while we upload your file',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading()
                    },
                })
                var file_data = $('#upload').prop('files')[0];
                var form_data = new FormData();
                form_data.append('file', file_data);
                $.ajax({// point to server-side PHP script
                    method : 'POST',
                    cache: false,
                    contentType: false,
                    processData: false,
                    data: form_data,
                    success: function(data){
                        data = JSON.parse(data);
                        if(data.status == '405'){
                            Swal.fire({
                                title: 'Method Not Allowed!',
                                text: data.message,
                                icon: 'error',
                                confirmButtonText: 'Yuh'
                            })
                        }else if(data.status == '422'){
                            Swal.fire({
                                title: 'Unprocessable Entity!',
                                text: data.message,
                                icon: 'error',
                                confirmButtonText: 'Cool'
                            })
                        }else if(data.status == '200'){
                            console.log(data.data_graph);
                            // Full Table Initiation
                            $('#tabs-full-table').html(`                    
                                <div class="bg-white rounded-xl items-center justify-center mb-5">
                                    <div class="mb-3 mx-auto w-1/2">
                                        <div class="relative mb-4 flex w-full flex-wrap items-stretch">
                                            <input
                                                id="datatable-full-search-input"
                                                type="search"
                                                class="relative m-0 -mr-0.5 block w-[1px] min-w-40 flex-auto rounded border border-solid border-neutral-300 bg-transparent bg-clip-padding px-3 py-[0.25rem] text-base font-normal leading-[1.6] text-neutral-700 outline-none transition duration-200 ease-in-out focus:z-[3] focus:border-primary focus:text-neutral-700 focus:shadow-[inset_0_0_0_1px_rgb(59,113,202)] focus:outline-none dark:border-neutral-600 dark:text-neutral-200 dark:placeholder:text-neutral-200 dark:focus:border-primary"
                                                placeholder="Search"
                                                aria-label="Search"
                                                aria-describedby="button-addon1" />
                                        </div>
                                    </div>
                                    <div id="datatable-full" data-te-max-height="460" data-te-fixed-header="true"></div>
                                </div>`
                            );
                            // define full table 
                            const full_data = {
                                columns: data.headers,
                                rows: data.data,
                            };
                            // creating data table instance with Tailwind Elements
                            const instanceFull = new te.Datatable(document.getElementById('datatable-full'), full_data)
                            document.getElementById('datatable-full-search-input').addEventListener('input', (e) => {
                                instanceFull.search(e.target.value);
                            });
                            $('#tabs-compact-table').html(`                    
                                <div class="bg-white rounded-xl items-center justify-center mb-5">
                                    <div class="mb-3 mx-auto w-1/2">
                                        <div class="relative mb-4 flex w-full flex-wrap items-stretch">
                                            <input
                                                id="datatable-compact-search-input"
                                                type="search"
                                                class="relative m-0 -mr-0.5 block w-[1px] min-w-40 flex-auto rounded border border-solid border-neutral-300 bg-transparent bg-clip-padding px-3 py-[0.25rem] text-base font-normal leading-[1.6] text-neutral-700 outline-none transition duration-200 ease-in-out focus:z-[3] focus:border-primary focus:text-neutral-700 focus:shadow-[inset_0_0_0_1px_rgb(59,113,202)] focus:outline-none dark:border-neutral-600 dark:text-neutral-200 dark:placeholder:text-neutral-200 dark:focus:border-primary"
                                                placeholder="Search"
                                                aria-label="Search"
                                                aria-describedby="button-addon1" />
                                        </div>
                                    </div>
                                    <div id="datatable-compact" data-te-max-height="460" data-te-fixed-header="true"></div>
                                </div>`
                            );
                            // define compacted table data
                            const compact_data = {
                                columns: data.headers,
                                rows: data.data_compacted,
                            };

                            const instanceCompact = new te.Datatable(document.getElementById('datatable-compact'), compact_data)
                            document.getElementById('datatable-compact-search-input').addEventListener('input', (e) => {
                                instanceCompact.search(e.target.value);
                            });

                            // for graph non-compacted/full
                            const full_graph = {};
                            $('#tabs-full-graph').html('');
                            for (const key in data.data_graph){
                                $('#tabs-full-graph').append(`
                                    <div class="mx-auto w-full overflow-hidden mb-5">
                                        <canvas id="`+key+`-chart"></canvas>
                                    </div>
                                `);
                                full_graph[key] = {
                                    type : 'line',
                                    data : {
                                        labels : data.data_graph[key].map(arr => arr[0]),
                                        datasets : [
                                            {
                                                label : key,
                                                data : data.data_graph[key].map(arr => arr[1]),
                                            },
                                        ],
                                    },
                                }
                                new te.Chart(document.getElementById(key+'-chart'),full_graph[key]);
                            }

                            // for graph compacted data
                            const compacted_graph = {};
                            $('#tabs-compact-graph').html('');
                            for (const key in data.data_compacted_graph){
                                $('#tabs-compact-graph').append(`
                                    <div class="mx-auto w-full overflow-hidden mb-5">
                                        <canvas id="`+key+`-chart"></canvas>
                                    </div>
                                `);
                                compacted_graph[key] = {
                                    type : 'line',
                                    data : {
                                        labels : data.data_compacted_graph[key].map(arr => arr[0]),
                                        datasets : [
                                            {
                                                label : key,
                                                data : data.data_compacted_graph[key].map(arr => arr[1]),
                                            },
                                        ],
                                    },
                                }
                                new te.Chart(document.getElementById(key+'-chart'),compacted_graph[key]);
                            }

                            Swal.fire({
                                title: 'Success!',
                                text: 'File Uploaded',
                                icon: 'success',
                                confirmButtonText: 'Cool'
                            })
                        }else{
                            Swal.fire({
                                title : 'Error!',
                                text : data,
                                icon : 'error',
                                confirmButtonText : 'Cool'
                            })
                        }
                    }
                });
            })
        })
    </script>
</html>