<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\AccessoriesMaster;
use DB;
use Auth;
use Session;
use App\BrandMaster;
use App\ProductCategoryMaster;
use App\ProductMaster;
use App\ModelMaster;
use App\StateMaster;
use App\WhatsappStatus;
use App\TempTaggingMaster;
use App\TaggingMaster1;
use App\SCPinMaster;
use App\ServiceCenter;
use App\SendToHO;
use Illuminate\Support\Facades\Log;


class SendWebhookController extends Controller
{
    
    public function send(Request $request)
    {

       $input = json_decode(file_get_contents('php://input'), true);
       
       //print_r($status);
     
      $contact = $input['contacts'][0];
      $message = $input['messages'][0];
      $statuses = $input['statuses'][0];

    if(empty($contact) && empty($message)) {
        exit();
    }

    $name = $contact['profile']['name'];
    $wa_id = $contact['wa_id'];


    $from = $message['from'];
    $session_id = $message['id'];
    $message_id = $message['id'];
    $text =    $message['text']['body'];
    $timestamp =    $message['timestamp'];
    $type = $message['type'];

          //Log::info($place_master);
         

    $text_list =    $message['interactive']['list_reply']['id'];

        $url = 'https://waba.whatsdesk.in/v1/messages.php';


        Log::info(file_get_contents('php://input'));

           $status = WhatsappStatus::whereRaw("wa_id='$wa_id'")->first();
           $def_lang = $status->language;
           $step = $status->step;
           $ticket_step = $status->ticket_step;
           //echo $lang;die;

  
        $length = strlen($text);

        if($text == "hi"  || $text == "Hi" || $text == "Hello" || $text == "hello")
        { 

              $NewMSG = "Welcome to Baltra
Press 1 for Hindi
Press 2 for English";
              $status = WhatsappStatus::whereRaw("wa_id='$wa_id'")->first();
              if(empty($status)){
              $w_status = new WhatsappStatus();

              $w_status->wa_id = $wa_id;
              $w_status->language = $text;
              $w_status->step = 0;
              $w_status->ticket_step = 0;

              $w_status->save(); 
              }
          
        }
        //check status
        else if($step == '0' && $ticket_step == '0')
        {
          if($text == '1'){
            $NewMSG = "अपनी शिकायत की जानकारी के लिए 1 दबाये |
          अपनी शिकायत दर्ज कराने के लिए  2 दबाये |";
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('language' => 'hindi','step' => '1','ticket_step' => '1'));
          }
          else{
            $NewMSG = "For Information About Your Complaint Press 1
To Register Your Complaint Press 2";
           $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('language' => 'english','step' => '1','ticket_step' => '1'));
          }

        }

        else if($def_lang == 'hindi' && $text == "1" && $step == '1' && $ticket_step != '19')
        {
        $NewMSG = "अपनी शिकायत की जानकारी के लिए कृपया 
        अपना 8 अंको वाला आवेदन संख्या बताये |";
        $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('step' => '2'));

        }

        else if($def_lang == 'hindi' &&  $step == '2' && $ticket_step != '19')
        { 
          if($length == '8'){
          $check = TaggingMaster::where("job_id",$text)->first();
          $data = json_decode($check,true);
          $case_status = $data['case_status'];

          $NewMSG = "आपकी आवेदन संख्या $text है 
           जिसकी स्थतिअभी $case_status है
            धन्यवाद !";
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->firstorfail()->delete();
          }else{
            $NewMSG ="आपकी आवेदन संख्या गलत है
             कृपया  दोबारा अंकित करे !";
          }
        }

        else if($def_lang == 'english' && $text == "1" && $step == '1' && $ticket_step != '19')
        {
        $NewMSG = "Please Enter Your 8 Digit
        Ticket Number for Check the Status |";
        $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('step' => '2'));
        }

        else if($def_lang == 'english' &&  $step == '2')
        { 
          $check = TaggingMaster::where("job_id",$text)->first();
          $data = json_decode($check,true);
          //print_r($data);die;
          if(!empty($data)){
          //$check = TaggingMaster::where("job_id",$text)->first();
          $case_status = $data['case_status']; 

          $NewMSG = "Your Enter Ticket Number
            is $text and it's 
            status is $case_status";
            $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->firstorfail()->delete();
          }
          else{
            $NewMSG ="Your Enter Ticket Number is Wrong
             please re enter your ticket no. !";
          }
        }
        // Ticket Creation in English
        else if($ticket_step == '1' && $text=="2")
        {
          if($def_lang == 'english'){
          $NewMSG = "Please Enter Your Name.";
          }else{
            $NewMSG = "कृपया अपना  नाम दर्ज करें.";
          }
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '2'));

          $temp = new TempTaggingMaster();
          $temp->wa_id = $wa_id;
          $temp->save(); 
        }
        else if($ticket_step == '2')
        {
          $lang = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('Customer_Name' => $text));
          //Log::info($lang);
          if($def_lang == 'english'){
            $NewMSG = "Please Enter Your Mobile Number.";
          }else{
            $NewMSG = "अपना मोबाइल नंबर दर्ज करें.";
          }
          
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '3'));
        }
        else if($ticket_step == '3')
        {
          //Log::info($length);
          if($length == '10' && is_numeric($text))
          {
            $lang = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('Contact_No' => $text));
            if($def_lang == 'english'){
              $NewMSG = "Please Enter Alternate Mobile Number.";
            }else{
              $NewMSG = "अपना वैकल्पिक मोबाइल नंबर दर्ज करें.";
            }
          
            $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '4'));
          }
          else{
            if($def_lang == 'english'){
              $NewMSG = "Re Enter Your Mobile No. ";
            }else{
              $NewMSG = "अपना मोबाइल नंबर फिर से दर्ज करें.";
            }
            
          }
        }
        else if($ticket_step == '4')
        {
          if($length == '10' && is_numeric($text))
          {
              $lang = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('alt_no' => $text));
              
              //$qr1 = "SELECT state_id,state_name FROM state_master st order by state_name limit 10";
              $qr1 = "SELECT DISTINCT st.state_id,st.state_name FROM state_master st inner join pincode_master pm on st.state_id = pm.state_id where pm.place != 'null' limit 10;";
              $state_json           =   DB::select($qr1);
              if($def_lang == 'english'){
                
                $NewMSG = 'Select State';
              }else{
                $NewMSG = 'राज्य चुनें';
              }
              
              $state_view = '';
              foreach($state_json as $state){
                $title = substr($state->state_name,0,24);
                $state_view .= '{
                  "id": "'.$state->state_name.'",
                  "title": "'.$title.'",
                  "description": ""          
                },';

              }
              $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '5'));
          }
          else{
            if($def_lang == 'english'){
              $NewMSG = "Re Enter Alternate Mobile No. ";
            }else{
              $NewMSG = "वैकल्पिक मोबाइल नंबर फिर से दर्ज करें.";
            }
          }
 
        }
        else if($ticket_step == '5')
        {
          
          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('state' => $text_list));
          $state = StateMaster::whereRaw("state_name = '$text_list'")->first();
          $state_id = $state->state_id;
          $pin_master = DB::select("SELECT Pin_Id,pincode from pincode_master where state_id='$state_id' limit 10");
          if($def_lang == 'english'){
            $NewMSG = "Please Select District";
          }else{
            $NewMSG = "कृपया जिला चुनें";
          }
          
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '6'));
          foreach($pin_master as $pin)
          {
            $title = substr($pin->pincode,0,24);
            $pin_view .= '{
              "id": "'.$pin->pincode.'",
              "title": "'.$title.'",
              "description": ""          
            },';
          }

        }

        else if($ticket_step == '6')
        {
          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('pincode' => $text_list));
          $pin_view = '';
          $status = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->first();
          $check = $status->State;
           
          $state = StateMaster::whereRaw("state_name = '$check'")->first();
          $state_id = $state->state_id;
          //Log::info($state_id);
          $place_master = DB::select("SELECT place from pincode_master where state_id='$state_id' and place!= 'null' order by place limit 10");
          //Log::info($place_master);
          if($def_lang == 'english'){
            $NewMSG = "Please Select City";
          }else{
            $NewMSG = "कृपया शहर का चयन करें";   
          }
          
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '7'));
          //Log::info($place_master);

          if(empty($place_master)){
  
            $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '6'));
            if($def_lang == 'english'){
              $NewMSG = "Not Available City Try Again";
            }else{
              $NewMSG = "शहर का नाम उपलब्ध नहीं है दोबारा  कोशिश करे  ";   
            }
            $place_master = DB::select("SELECT place from pincode_master where place!= 'null' order by place DESC limit 10");

          }
      //Log::info($place_master);
          foreach($place_master as $pl)
          {
            $title = substr($pl->place,0,24);
            $pin_view .= '{
              "id": "'.$pl->place.'",
              "title": "'.$title.'",
              "description": ""          
            },';

          }
          

        }

        else if($ticket_step == '7')
        {
          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('city' => $text_list));
          
          if($def_lang == 'english'){
            $NewMSG = "Please Enter Your Communication Address.";
          }else{
            $NewMSG = "कृपया अपना संचार पता दर्ज करें.";  
          }
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '8'));

        }
        else if($ticket_step == '8')
        {
          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('Customer_Address' => $text));
          
          if($def_lang == 'english'){
            $NewMSG = "Please Enter Landmark.";
          }else{
            $NewMSG = "कृपया निकट स्थान दर्ज करें.";  
          }
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '9'));

        }
        else if($ticket_step == '9')
        {
          $pin_view = '';

          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('Customer_Address_Landmark' => $text));

          $qr1 = "SELECT DISTINCT(category) FROM product_master where product_status='1' limit 10;";
          $category_json           =   DB::select($qr1);

          
          if($def_lang == 'english'){
            $NewMSG = "Please Select Product Category.";
          }else{
            $NewMSG = "कृपया उत्पाद श्रेणी का चयन करें.";  
          }
          
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '10'));

          foreach($category_json as $categories)
          {
            $title = substr($categories->category,0,20);
            $pin_view .= '{
              "id": "'.$categories->category.'",
              "title": "'.$title.'",
              "description": ""          
            },';
          }


        }
        else if($ticket_step == '10')
        {
          $pin_view = '';

          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('category' => $text_list));

          $status = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->first();
          $check = $status->category;

          $product_master = DB::select("SELECT pm.category,pm.product_id,pm.product_name FROM product_master pm 
          WHERE product_status='1' and category='$check' limit 10");

          
          if($def_lang == 'english'){
            $NewMSG = "Please Select Product Name.";
          }else{
            $NewMSG = "कृपया उत्पाद का नाम चुनें.";
          }

          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '11'));

          foreach($product_master as $product)
          {
            $title = substr($product->product_name,0,24);
            $pin_view .= '{
              "id": "'.$product->product_name.'",
              "title": "'.$title.'",
              "description": ""          
            },';
          }


        }

        else if($ticket_step == '11')
        {
          $pin_view = '';

          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('product_name' => $text_list));

          $status = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->first();
          $check_cate = $status->category;
          $check_pname = $status->product_name;

          $product_code = DB::select("SELECT pm.product_id,pm.product_code FROM product_master pm 
          WHERE product_status='1' and category='$check_cate' and product_name='$check_pname' limit 10");
   

          
          if($def_lang == 'english'){
            $NewMSG = "Please Select Product Code.";
          }else{
            $NewMSG = "कृपया उत्पाद कोड चुनें.";
          }

          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '12'));

          foreach($product_code as $code)
          {
            $title = substr($code->product_code,0,24);
            $pin_view .= '{
              "id": "'.$code->product_id.'",
              "title": "'.$title.'",
              "description": ""          
            },';
          }


        }

        else if($ticket_step == '12')
        {
          $pin_view = '';

          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('product_id' => $text_list));

          $status = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->first();
          $check_cate = $status->category;

          $product_error = DB::select("SELECT  cem.category,cem.error FROM category_error_message cem
          WHERE category='$check_cate' limit 10");

          if($def_lang == 'english'){
            $NewMSG = "Please Select Product Category Error Message.";
          }else{
            $NewMSG = "कृपया उत्पाद श्रेणी त्रुटि संदेश चुनें.";
          }

          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '13'));

          foreach($product_error as $message)
          {
            $title = substr($message->error,0,24);
            $pin_view .= '{
              "id": "'.$message->error.'",
              "title": "'.$title.'",
              "description": ""          
            },';
          }


        }
        else if($ticket_step == '13')
        {
          $pin_view = '';

          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('category_error' => $text_list));
   
          

          if($def_lang == 'english'){
            $NewMSG = "Please Select Warranty Status.";
          }else{
            $NewMSG = "कृपया वारंटी स्थिति चुनें.";
          }

          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '14'));

          

            $pin_view .= '{
              "id": "In warranty",
              "title": "In warranty",
              "description": ""          
            },
            {
              "id": "out of warranty",
              "title": "out of warranty",
              "description": ""          
            },';


        }
        else if($ticket_step == '14')
        {
          $pin_view = '';

          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('warranty_card' => $text_list));
   
          if($def_lang == 'english'){
            $NewMSG = "Please Select Invoice.";
          }else{
            $NewMSG = "कृपया चालान का चयन करें.";
          }

          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '15'));

          

            $pin_view .= '{
              "id": "Yes",
              "title": "Yes",
              "description": ""          
            },
            {
              "id": "No",
              "title": "No",
              "description": ""          
            },';


        }

        else if($ticket_step == '15')
        {
          $pin_view = '';

          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('invoice' => $text_list));
   
          

          if($def_lang == 'english'){
            $NewMSG = "Please Select Purchase From.";
          }else{
            $NewMSG = "कृपया खरीद का चयन करें.";
          }

          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '16'));

          

            $pin_view .= '{
              "id": "Online",
              "title": "Online",
              "description": ""          
            },
            {
              "id": "Retailer",
              "title": "Retailer",
              "description": ""          
            },';


        }
        else if($ticket_step == '16')
        {
          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('purchase_from' => $text_list));
   
          

          if($def_lang == 'english'){
            $NewMSG = "Please Enter Serial Number.";
          }else{
            $NewMSG = "कृपया सीरियल नंबर दर्ज करें.";
          }

          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '17'));


        }
        else if($ticket_step == '17')
        {
          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('Serial_No' => $text));
   
          if($def_lang == 'english'){
            $NewMSG = "Please Enter Date of Purchase(yyyy-mm-dd).";
          }else{
            $NewMSG = "कृपया खरीद की तिथि दर्ज करें (yyyy-mm-dd).";
          }

          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '18'));


        }
        else if($ticket_step == '18')
        {
          //$date=date_create("03/03/2015");
          //$text1 = date_format($text,"yyyy-mm-dd");
          $save = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->update(array('Bill_Purchase_Date' => $text));
   
          $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->update(array('ticket_step' => '19'));
          $status = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->first();
          //Log::info($status);
          if($def_lang == 'english'){
            $NewMSG = "Name  - $status->Customer_Name
Mobile Number - $status->Contact_No
Alternate Number - $status->alt_no
State - $status->State 
District - $status->Pincode
City - $status->City
Address - $status->Customer_Address
Landmark - $status->Customer_Address_Landmark
Product Category - $status->category
product Name - $status->product_name
Serial Number - $status->Serial_No
Date of Purchase - $status->Bill_Purchase_Date
Warranty card status - $status->warranty_card
Invoice - $status->invoice
Purchase From - $status->purchase_from
Product Category Error Message - $status->category_error

Please Check All Details And Confirm for press 1.";
          }else{
            $NewMSG = "नाम  - $status->Customer_Name
मोबाइल नंबर - $status->Contact_No
वैकल्पिक नंबर - $status->alt_no
राज्य - $status->State 
जिला  - $status->Pincode
शहर  - $status->City
पता  - $status->Customer_Address
निकट स्थान  - $status->Customer_Address_Landmark
उत्पाद श्रेणी - $status->category
उत्पाद का नाम - $status->product_name
सीरियल नंबर - $status->Serial_No
खरीद की तिथि - $status->Bill_Purchase_Date
वारंटी कार्ड की स्थिति - $status->warranty_card
चालान का चयन - $status->invoice
खरीद का चयन - $status->purchase_from
उत्पाद श्रेणी त्रुटि संदेश - $status->category_error
          
कृपया सभी विवरण जांचें और पुष्टि के लिए 1 दबाएं.";
          }
          


        }
        else if($ticket_step == '19')
        {
          if($text == '1')
          {
            $status = TempTaggingMaster::whereRaw("wa_id='$wa_id'")->first();
            $taggingArr            =   new TaggingMaster1();

            $taggingArr->Customer_Name=$status->Customer_Name;
            $taggingArr->Contact_No=$status->Contact_No;
            $taggingArr->alt_no=$status->alt_no;
            $taggingArr->Customer_Address=$status->Customer_Address;
            $taggingArr->state=$status->State;
            $taggingArr->city=$status->City;
            $state_code_arr = StateMaster::whereRaw("state_name='$status->State'")->first();
            $state_code = $state_code_arr->state_code;   
            $state_id = $state_code_arr->state_id;
            $taggingArr->state_code=$state_code;
            $taggingArr->pincode=$status->Pincode;

            $product_det           =   ProductMaster::whereRaw(" product_id='$status->product_id'")->first(); 
            $product_code = $product_det->product_code;

            $taggingArr->category=$status->category;
            $taggingArr->product_code=$product_code; 
            $taggingArr->product_id=$status->product_id;
            $taggingArr->product_name=$status->product_name; 
            $taggingArr->Product_Detail=$status->category;
            $taggingArr->Product=$status->product_name;
            $taggingArr->Customer_Address_Landmark=$status->Customer_Address_Landmark;
            
            $taggingArr->Serial_No=$status->Serial_No;
            $taggingArr->Bill_Purchase_Date=$status->Bill_Purchase_Date;
            //$taggingArr->asc_code=$asc_code;
            $taggingArr->warranty_card=$status->warranty_card;
            $taggingArr->invoice=$status->invoice;
    
    
            $taggingArr->purchase_from=$status->purchase_from;
    
            $taggingArr->category_error=$status->category_error;
             
            
            $alloc_qry = "Case Not Allocated.";

                
                $center_det = $this->allocate_center($state_id,$pincode,array());
                if(!empty($center_det))
                {
                    $center_id=$center_det->center_id;
                    $taggingArr->center_id=$center_id;
                    $taggingArr->center_allocation_date=date('Y-m-d H:i:s');
                    $taggingArr->asc_code=$center_det->asc_code;
                    $alloc_qry = "And Case Allocated To ASC ".$center_det->asc_code;
                }
            
            $job_id_Arr = $this->job_id();

            $job_code = $job_id_Arr['job_code'];
             
            $taggingArr->job_id=$job_code;
            $taggingArr->job_year=$job_id_Arr['year'];
            $taggingArr->job_month=$job_id_Arr['month'];
            $taggingArr->sr_no=$job_id_Arr['sr_no'];
            $taggingArr->case_status='Open';
            $taggingArr->center_remark='';
            $taggingArr->save();

                if($taggingArr->save()){
                  $TagId = $taggingArr->id;  

                  $send_to_ho_array = new SendtoHo();

                  $send_to_ho_array->job_id=$job_code;
                  $send_to_ho_array->complain_date=date('d-M-Y');
                  $send_to_ho_array->case_status='Part Pending';

                  $send_to_ho_array->category=$status->category;

                  $send_to_ho_array->product_name=$status->product_name; 
                  $send_to_ho_array->product_code=$product_code; 

                  $send_to_ho_array->Customer_Name=$status->Customer_Name;
                  $send_to_ho_array->Customer_Address=$status->Customer_Address;
                  $send_to_ho_array->state=$status->State;
                  $send_to_ho_array->Contact_No=$status->Contact_No;
                  $send_to_ho_array->pincode=$status->Pincode;

                  $state_code_arr = StateMaster::whereRaw("state_name='$status->State'")->first();
                  $state_code = $state_code_arr->state_code;   
                  $state_id = $state_code_arr->state_id;
                  
                  
                  

                  $send_to_ho_array->send_to_ho='0';
                  $send_to_ho_array->created_at=date('Y-m-d H:i:s'); 
                  
                  $send_to_ho_array->save();

                  Session::flash('message', "Sr No. $job_code Added Successfully For $status->Customer_Name.".$alloc_qry);
                  Session::flash('alert-class', 'alert-danger');
                  $msg = "Thank you for calling BALTRA. Complaint $job_code has been registered. In case issue is not resolved within 72 working hrs you may call on 9599446808 on working hrs";
                  $sms = array('PhNo'=>$status->Contact_No,'MSG'=>$msg);
                  //$log_id = $this->sms_send($sms);
                  
                  $taggingUpdate = array();
                  
                  $taggingUpdate['sms_text'] = $msg;
                  $taggingUpdate['sms_date'] = date('Y-m-d H:i:s');
                  $taggingUpdate['sms_log_id'] = $log_id;
                  TaggingMaster1::whereRaw("TagId='$TagId'")->update($taggingUpdate);

                  if($def_lang == 'english'){
                    $NewMSG = "Sr No. $job_code Added Successfully For $status->Customer_Name.".$alloc_qry;
                    $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->firstorfail()->delete();
                    TempTaggingMaster::where('wa_id', $wa_id)->delete();

                  }else{
                    $NewMSG = "क्रमांक. $job_code , $status->Customer_Name के लिए सफलतापूर्वक जोड़ा गया .";
                    $lang = WhatsappStatus::whereRaw("wa_id='$wa_id'")->firstorfail()->delete();
                    TempTaggingMaster::where('wa_id', $wa_id)->delete();

                  }
                  
                  
              }
          }

        }

        else{
          $NewMSG = "Please enter a correct input.";
        }
    
        $save_state = rtrim($state_view,",");
        $save_pin = rtrim($pin_view,",");
        //$save_city = rtrim($city_view,",");
        //Log::info($save_pin);
        if($wa_id!="") 
        {

          $from1 = json_encode($from);
          $new = json_encode($NewMSG);

          if($ticket_step == '5'){
            $list = '';
               $button = '';
              if($def_lang == 'english'){
                  $list .= 'list';
                  $button .= 'Choose';
              }else{
                $list .= 'सूची';
                $button .= 'चुनें';
              }
               $data = '{
                 "to": '.$from1.',
                 "recipient_type": "individual",
                 "type": "interactive",
                 "interactive": {
                   "type": "list",
                   "header": {
                     "type": "text",
                     "text": ""
                   },
                   "body": {
                     "text": '.$new.'
                   },
                   "footer": {
                     "text": ""
                   },
                   "action": {
                     "button": "'.$button.'",
                     "sections":[
                       {
                         "title":"'.$list.'",
                         "rows": [
                           '.$save_pin.'
                         ]
                       }
                     ]
                   }
                 }
               }
               ';
             }
             if($ticket_step == '6'){
              $list = '';
               $button = '';
              if($def_lang == 'english'){
                  $list .= 'list';
                  $button .= 'Choose';
              }else{
                $list .= 'सूची';
                $button .= 'चुनें';
              }
              $data = '{
                "to": '.$from1.',
                "recipient_type": "individual",
                "type": "interactive",
                "interactive": {
                  "type": "list",
                  "header": {
                    "type": "text",
                    "text": ""
                  },
                  "body": {
                    "text": '.$new.'
                  },
                  "footer": {
                    "text": ""
                  },
                  "action": {
                    "button": "'.$button.'",
                    "sections":[
                      {
                        "title":"'.$list.'",
                        "rows": [
                          '.$save_pin.'
                        ]
                      }
                    ]
                  }
                }
              }
              ';
            }
            if($ticket_step == '10'){
              $list = '';
               $button = '';
              if($def_lang == 'english'){
                  $list .= 'list';
                  $button .= 'Choose';
              }else{
                $list .= 'सूची';
                $button .= 'चुनें';
              }
              $data = '{
                "to": '.$from1.',
                "recipient_type": "individual",
                "type": "interactive",
                "interactive": {
                  "type": "list",
                  "header": {
                    "type": "text",
                    "text": ""
                  },
                  "body": {
                    "text": '.$new.'
                  },
                  "footer": {
                    "text": ""
                  },
                  "action": {
                    "button": "'.$button.'",
                    "sections":[
                      {
                        "title":"'.$list.'",
                        "rows": [
                          '.$save_pin.'
                        ]
                      }
                    ]
                  }
                }
              }
              ';
            }
            

          if($type=='text') 
          {
            if($ticket_step == '4')
            { if($length == '10' && is_numeric($text))
              { 
              $list = '';
               $button = '';
              if($def_lang == 'english'){
                  $list .= 'list';
                  $button .= 'Choose';
              }else{
                $list .= 'सूची';
                $button .= 'चुनें';
              }
              $data = '{
                "to": '.$from1.',
                "recipient_type": "individual",
                "type": "interactive",
                "interactive": {
                  "type": "list",
                  "header": {
                    "type": "text",
                    "text": ""
                  },
                  "body": {
                    "text": '.$new.'
                  },
                  "footer": {
                    "text": ""
                  },
                  "action": {
                    "button": "'.$button.'",
                    "sections":[
                      {
                        "title":"'.$list.'",
                        "rows": [
                          '.$save_state.'
                        ]
                      }
                    ]
                  }
                }
              }
              ';
            }else{
              $data = '{
                "to": '.$from1.',
                "type": "text",
                "recipient_type": "individual",
                "text": {
                  "body": '.$new.'
                }
              }
              ';
            }
            }

           else if($ticket_step == '9')
            {
              $list = '';
               $button = '';
              if($def_lang == 'english'){
                  $list .= 'list';
                  $button .= 'Choose';
              }else{
                $list .= 'सूची';
                $button .= 'चुनें';
              }
              $data = '{
                "to": '.$from1.',
                "recipient_type": "individual",
                "type": "interactive",
                "interactive": {
                  "type": "list",
                  "header": {
                    "type": "text",
                    "text": ""
                  },
                  "body": {
                    "text": '.$new.'
                  },
                  "footer": {
                    "text": ""
                  },
                  "action": {
                    "button": "'.$button.'",
                    "sections":[
                      {
                        "title":"'.$list.'",
                        "rows": [
                          '.$save_pin.'
                        ]
                      }
                    ]
                  }
                }
              }
              ';
            }
           
          else{

            $data = '{
              "to": '.$from1.',
              "type": "text",
              "recipient_type": "individual",
              "text": {
                "body": '.$new.'
              }
            }
            ';
          }
         
             $curl = curl_init();
             
              
              curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://waba.whatsdesk.in/v1/messages.php',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                  'API-KEY: xR1MOeD4HBQb9xQKSxpTfAmbAK',
                  'Content-Type: application/json'
                ),
              ));

              $response = curl_exec($curl);

              curl_close($curl);
              echo $response;

          }

        else if($type=='interactive')
        {

          if($ticket_step == '7'){
            $data = '{
              "to": '.$from1.',
              "type": "text",
              "recipient_type": "individual",
              "text": {
                "body": '.$new.'
              }
            }
            ';
          }
          else if($ticket_step == '16'){
            $data = '{
              "to": '.$from1.',
              "type": "text",
              "recipient_type": "individual",
              "text": {
                "body": '.$new.'
              }
            }
            ';
          }else{
            $list = '';
               $button = '';
              if($def_lang == 'english'){
                  $list .= 'list';
                  $button .= 'Choose';
              }else{
                $list .= 'सूची';
                $button .= 'चुनें';
              }
            $data = '{
              "to": '.$from1.',
              "recipient_type": "individual",
              "type": "interactive",
              "interactive": {
                "type": "list",
                "header": {
                  "type": "text",
                  "text": ""
                },
                "body": {
                  "text": '.$new.'
                },
                "footer": {
                  "text": ""
                },
                "action": {
                  "button": "'.$button.'",
                  "sections":[
                    {
                      "title":"'.$list.'",
                      "rows": [
                        '.$save_pin.'
                      ]
                    }
                  ]
                }
              }
            }
            ';
          }
          $from1 = json_encode($from);
          $new = json_encode($NewMSG);

          $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://waba.whatsdesk.in/v1/messages.php',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS =>$data,
              CURLOPT_HTTPHEADER => array(
                'API-KEY: xR1MOeD4HBQb9xQKSxpTfAmbAK',
                'Content-Type: application/json'
              ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            echo $response;
        }
                      }
       

        }

    public function allocate_center($state_id,$pincode,$sc_id)
    {
        $center_qry = "";
        if(!empty($sc_id))
        {
            $sc_id_str = implode(",",$sc_id);
            $center_qry = " and center_id not in ($sc_id_str)";
        }
        $pincode_exist = SCPinMaster::whereRaw("state_id='$state_id' and pincode='$pincode' $center_qry")->first();
            
            if( !empty($pincode_exist))
            {
                $center_id = $pincode_exist->center_id;
                $center_det = ServiceCenter::whereRaw("center_id='$center_id' and sc_status='1'")->first();
                if(empty($center_det))
                {
                    $sc_id[] = $center_id;
                    return $this->allocate_center($state_id,$pincode,$sc_id);
                }
                else
                {
                    return $center_det;
                }
            }
            else
            {
                return array();
            }
    }

    public function job_id()
    {
        $year = date('y');
        $month = date('m');
        $month_name = strtoupper(date('m'));
        
        $qr_max_no = "SELECT MAX(sr_no) srno FROM `tagging_master` WHERE  job_year='$year' AND job_month='$month'";
        $max_json           =   DB::select($qr_max_no);
        $max = $max_json[0];
        $sr_no = $max->srno;
        
        $str_no = "0000";
        $sr_no = $sr_no+1;
        $len = strlen($str_no);
        $newlen = strlen("$sr_no");
        $new_no = substr_replace($str_no, $sr_no, $len-$newlen,$newlen);
        //$short_brand_name = substr($brand_name, 0, 2);
        $job_code = "$year"."$month_name".$new_no;
        return array('job_code'=>$job_code,'year'=>$year,'month'=>$month,'sr_no'=>$sr_no);
    }
    
    
}

