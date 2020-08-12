<?php
define('TOKEN', '1134625090:AAEBq85oUxMTpt0eEuCv63DEOjBNnPtWD6k');
define('BASE_URL', 'https://api.telegram.org/bot' . TOKEN . '/');
define('FILE_URL', "https://api.telegram.org/file/bot" . TOKEN . "/");
define('LOCATION_URL', 'https://ctc.pp.ua/genie/');
define('CHOICES', 'username name text');
define('MARGE', 20);

class Database{

  protected $db, $prev_command;
  public function __construct($db_link, $user_id){
    $this->db = new \PDO($db_link);
    $this->prev_command = $this->db->query("SELECT `Text` FROM `message` WHERE `Date` = (SELECT MAX(`Date`) FROM `message` WHERE Id_user = $user_id);")->fetchAll()[0][0];
  }

  private function print_test_db($result_string){
    return "В базе данных хранится следующая информация:\n\nId: ".$result_string[0]["Id_user"]."\n\nUsername: @".$result_string[0]["Username"]."\n\nName: ".$result_string[0]["First_name"]. " ". $result_string[0]["Last_name"];
  }

  protected function check_user($id, $update){
    $user = $this->db->query("SELECT * FROM user WHERE Id_user = $id;")->fetchAll();
    if(count($user) === 0){
      $query_insert = "INSERT INTO user(Id_user, Username, First_name, Last_name, Language_code, Chat_id) VALUES(". $update["id"]. ", \"" . 
        $update["username"] . "\", \"" . $update["first_name"]. "\" , \"" . $update["last_name"] . "\", \"" . $update["language_code"] . "\", " . $update["id"] . ");";
      $insert_bool = $this->db->exec($query_insert);
      return $insert_bool;
    }
    return true;
  }

  protected function add_message_to_db($id_user, $date, $text = 'NULL', $type = 'NULL', $file_id = 'NULL', $file_un_id = 'NULL', $file_size = 'NULL', $width = 'NULL', $height = 'NULL'){
    $id = $this->db->query('SELECT MAX(Id_message) FROM `message`;')->fetchAll()[0][0] + 1;
    $query_ms_text = "INSERT INTO `message` (Id_user, Id_message, `Date`, `Text`, `File_id`, File_unique_id, File_size, Width, Height, Attachment_type) 
    VALUES ( $id_user ,  $id , $date , '$text', '$file_id', '$file_un_id', $file_size, $width, $height, '$type' );";   
    $result = $this->db->exec($query_ms_text);
    return $result;
  }

  public function __destruct(){ }

}

class Bot extends Database{
  
  public $update, $chat_id;

  public function __construct(){  
    $this->update = json_decode(file_get_contents('php://input'), JSON_OBJECT_AS_ARRAY);
    $this->chat_id = (int) $this->update['message'] ['chat']['id'];
    parent :: __construct('sqlite:db/genie_bot.db', $this->chat_id);
    $this->check_user($this->chat_id, $this->update["message"]["from"]);
    if(isset($this->update['message'] ['text']) && $this->update['message'] ['text'][0] == '/'){
      $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => $this->check_command($this->update['message'] ['text']) ]);
    }
    else{
      $this->check_sequence($this->prev_command, $this->update['message'] ['text']);
    }
  }

  public function sendRequest($method, $params = []){
    $url = BASE_URL . $method;
    if(!empty($params)){
        $url .= "?" . http_build_query($params);
    }
    return json_decode(
        file_get_contents($url), 
        JSON_OBJECT_AS_ARRAY
    );
  }

  private function check_command($text){
    switch($text){
      case '/start':
        $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "command");
        return implode(file('start.txt'));
      break;
  
      case '/help':
        $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "command");        
        return implode(file('help.txt'));
      
      case '/wish_genie_watermark':
        $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "command");
        return 'Господин, будьте добры, отправьте мне изображение для нанесения водяного знака.';
        
      case '/wish_text_watermark':
        $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "command");
        return implode(file('choose_list.txt'));
  
      case '/wish_my_watermark':
        $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "command");
        return 'Господин, будьте добры, отправьте мне изображение для нанесения водяного знака.'; 
        
      case '/wish_invisible_watermark':
        $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "command");
        return implode(file('choose_list.txt'));
  
      case '/get_invisible_watermark':
        $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "command");
        return 'Господин, будьте добры, отправьте мне изображение для считывание водяного знака, выбрав опцию "как файл". Это поможет сохранить скрытую информацию при передаче.'; 
  
      default:
        return 'Простите, Господин, но я не знаю такой команды. Сожалею, что разочаровал Вас. Воспользуйтесь командой /help, чтобы узнать о моих скромных возможностях.';
    }
  }

  private function check_sequence($prev_command, $text){
    if (isset($this->update['message'] ['photo']) || isset($this->update['message'] ['document'])){
      switch(explode('*', $prev_command)[0]){
        case '/wish_genie_watermark':
          $image = new Image($this, $prev_command);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "photo", $image->id);
        break;

        case '/wish_text_watermark username':
          $image = new Image($this, $prev_command);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "photo", $image->id);      
        break;


        case '/wish_text_watermark set_text':
          $image = new Image($this, $prev_command);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "photo", $image->id);
        break;

        case '/wish_text_watermark name':
          $image = new Image($this, $prev_command);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "photo", $image->id);
         break;
  
        case '/wish_my_watermark':
          if (isset($this->update['message'] ['photo'])){
            $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], "wait_my_watermark", "photo", $this->update['message'] ['photo'] [1] ['file_id']);
          }
          else{
            $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], "wait_my_watermark", "photo", $this->update['message'] ['document']['file_id']);
          }
          $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => 'Господин, будьте добры, отправьте мне изображение, которое хотите использовать в качестве водяного знака.']); 
        break;
        
        case 'wait_my_watermark':
          $main_photo_id = $this->db->query("SELECT `File_id` FROM `message` WHERE `Date` = (SELECT MAX(`Date`) FROM `message` WHERE Id_user =  $this->chat_id);")->fetchAll()[0][0];
          $image = new Image($this, $prev_command, $main_photo_id);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "photo", $image->id);
          
        break;
        
        case '/wish_invisible_watermark username':
          $image = new Image($this, $prev_command);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text , "photo", $image->id);
          if ($text == 'text') {
            $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => 'Господин, будьте добры, отправьте мне текст для водяного знака.']);
          }
        break;

        case '/wish_invisible_watermark set_text':
          $image = new Image($this, $prev_command);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "photo", $image->id);
         break;

        case '/wish_invisible_watermark name':
          $image = new Image($this, $prev_command);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text . ' ' . $text, "photo", $image->id);
         break;
  
        case '/get_invisible_watermark':
          $image = new Image($this, $prev_command);
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $text, "photo", $image->id);
        break;
  
        default:
          $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => "Господин, будьте добры, выберите команду. На данный момент я могу предложить Вам воспользоваться командой /wish_genie_watermark, /wish_text_watermark, /wish_my_watermark и /wish_invisible_watermark, с помощью которых я могу нанести на Ваше изображение видимый водяной знак. Подробней с моими возможностями можно ознакомиться здесь: /help"]);
      }
    }
    else if (strpos(CHOICES, $text) !== false) {
      switch($prev_command){
        case '/wish_text_watermark':
         $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $prev_command . ' ' . $text, "photo");
          if ($text == 'text') {
            $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => 'Господин, будьте добры, отправьте мне текст для водяного знака.']);
          }
          else {
            $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => 'Господин, будьте добры, отправьте мне изображение для нанесения водяного знака.']);
          }
          
        break;
  
        case '/wish_invisible_watermark':
          $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], $prev_command . ' ' . $text, "photo");
          if ($text == 'text') {
            $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => 'Господин, будьте добры, отправьте мне текст для водяного знака.']);
          }
          else {
            $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => 'Господин, будьте добры, отправьте мне изображение для нанесения водяного знака.']);
          }
        break;
      }
    }
    else if ($prev_command == '/wish_invisible_watermark text' || $prev_command == '/wish_text_watermark text'){
      $this->add_message_to_db($this->update["message"]["from"]["id"], $this->update["message"]["date"], explode(" ", $prev_command)[0] . " set_text*" . $text, "command");
      $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => 'Господин, будьте добры, отправьте мне изображение для нанесения водяного знака.']);
    }    
        
    else {
      $this->sendRequest('sendMessage', ['chat_id' => $this->chat_id, 'text' => 'Простите, Господин, но я не знаю такой команды. Сожалею, что разочаровал Вас. Воспользуйтесь командой /help, чтобы узнать о моих скромных возможностях.']);
    }
  }

  public function __destruct(){ }
}

class Image {
  private $bot, $image, $name, $path, $main_photo_id;
  public $id;
  
  public function __construct($bot, $prev_command, $main_photo_id = NULL){
    $this->bot = $bot;
    $this->main_photo_id = $main_photo_id;
    if (isset($this->bot->update['message'] ['photo'])){
      $this->id = $this->bot->update['message'] ['photo'] [1] ['file_id'];
    }
    else {
      $this->id = $this->bot->update['message'] ['document'] ['file_id'];
    }
    $this->path = $this->get_file_path($this->id);
    $this->image = $this->get_image($this->path);
    $this->check_command($prev_command);
  }

  private function transliteration ($text){
    $translit = array('а'=>'a','б'=>'b','в'=>'v','г'=>'g', 'ґ'=> 'g', 'д'=>'d','е'=>'e','ё'=>'e', 'ї' => 'yi', 'і' => 'i', 'є' => 'ye', 'ж'=>'j','з'=>'z','и'=>'y','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>'', 'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G', 'Ґ'=> 'G', 'Д'=>'D','Е'=>'E','Ё'=>'E', 'Є' => 'YE', 'Ї' => 'YI', 'І' => 'I', 'Ж'=>'J','З'=>'Z','И'=>'Y','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C','Ч'=>'CH','Ш'=>'SH','Щ'=>'SHCH','Ы'=>'Y','Э'=>'E','Ю'=>'YU','Я'=>'YA','Ъ'=>'','Ь'=>'');
    $result =  strtr($text, $translit);
    return $result;
  }

  private function check_command($command) {
   switch(explode('*', $command)[0]) {

      case '/wish_genie_watermark':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        $this->add_watermark( $this->image, $this->get_image('genie-watermark.png'), (int) getimagesize($this->path)[0], 5);
        $this->bot->sendRequest('sendDocument', ['chat_id' => $this->bot->chat_id, 'document' => LOCATION_URL . $this->name ]);
      break;

      case '/wish_text_watermark username':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        if (isset($this->bot->update ['message'] ['from'] ['username'])){
          $text = '@' . $this->bot->update ['message'] ['from'] ['username'];
        }
        else {
          $text = 'anonymous';
        }
        $text_wm = $this->create_text_watermark('@' . $this->bot->update ['message'] ['from'] ['username'], 'background_transparent.png');
        $this->add_watermark($this->image, $text_wm, (int) getimagesize($this->path)[0], 3);
        $this->bot->sendRequest('sendDocument', ['chat_id' => $this->bot->chat_id, 'document' => LOCATION_URL . $this->name ]);
      break;

      case '/wish_text_watermark name':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        if (isset($this->bot->update ['message'] ['from'] ['first_name']) || isset($this->bot->update ['message'] ['from'] ['last_name'])){
          $text = $this->transliteration( $this->bot->update ['message'] ['from'] ['first_name'] . ' ' .$this->bot->update ['message'] ['from'] ['last_name'] );
        }
        else {
          $text = 'anonymous';
        }
        $text_wm = $this->create_text_watermark($text, 'background_transparent.png');
        $this->add_watermark($this->image, $text_wm, (int) getimagesize($this->path)[0], 3);
        $this->bot->sendRequest('sendDocument', ['chat_id' => $this->bot->chat_id, 'document' => LOCATION_URL . $this->name ]);
      break;

      case '/wish_text_watermark set_text':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        $text_wm = $this->create_text_watermark($this->transliteration(substr($command, strpos($command, '*') + 1)), 'background_transparent.png');
        $this->add_watermark($this->image, $text_wm, (int) getimagesize($this->path)[0], 2);
        $this->bot->sendRequest('sendDocument', ['chat_id' => $this->bot->chat_id, 'document' => LOCATION_URL . $this->name ]);
      break;

      case 'wait_my_watermark':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        $this->add_my_watermark($this->main_photo_id);
        $this->bot->sendRequest('sendDocument', ['chat_id' => $this->bot->chat_id, 'document' => LOCATION_URL . $this->name ]);
      break;
      
      case '/wish_invisible_watermark username':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        if (isset($this->bot->update ['message'] ['from'] ['username'])){
          $text = '@' . $this->bot->update ['message'] ['from'] ['username'];
        }
        else {
          $text = 'anonymous';
        }
        $this->add_invisible_watermark($this->image, $text, getimagesize($this->path));
        $this->bot->sendRequest('sendDocument', ['chat_id' => $this->bot->chat_id, 'document' => LOCATION_URL . $this->name ]);
      break;

      case '/wish_invisible_watermark name':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        if (isset($this->bot->update ['message'] ['from'] ['first_name']) || isset($this->bot->update ['message'] ['from'] ['last_name'])){
          $text = $this->bot->update ['message'] ['from'] ['first_name'] . ' ' . $this->bot->update ['message'] ['from'] ['last_name'];
        }
        else {
          $text = 'anonymous';
        }
        $this->add_invisible_watermark($this->image, $text, getimagesize($this->path));
        $this->bot->sendRequest('sendDocument', ['chat_id' => $this->bot->chat_id, 'document' => LOCATION_URL . $this->name ]);
      break;

      case '/wish_invisible_watermark set_text':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        $this->add_invisible_watermark($this->image, substr($command, strpos($command, '*') + 1), getimagesize($this->path));
        $this->bot->sendRequest('sendDocument', ['chat_id' => $this->bot->chat_id, 'document' => LOCATION_URL . $this->name ]);
      break;

      case '/get_invisible_watermark':
        $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Слушаю и повинуюсь."]);
        $invisible_wm = $this->get_invisible_watermark($this->image, getimagesize($this->path));
        if ($invisible_wm === false) {
          $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Изображение не содержит невидимый водяной знак. Возможно, сведения исказились при передаче. Попробуйте ещё раз, отправив изображение с водяным знаком 'как файл'"]);
        }
        else {
          $this->bot->sendRequest('sendMessage', ['chat_id' => $this->bot->chat_id, 'text' => "Невидимый водяной знак содержит следующий текст: " .  $invisible_wm]);
        }
      break;
    }
  }

  private function get_image($file_path){
    $type_img = explode (".", $file_path);
    switch(strtolower($type_img[count($type_img)-1])) {
      case "png": $image = imagecreatefrompng($file_path); break;
      case "jpg": $image = imagecreatefromjpeg($file_path); break;
      case "jpeg": $image = imagecreatefromjpeg($file_path); break;
      case "gif": $image = imagecreatefromgif($file_path); break;
      default: $image = imagecreatefromgd ($file_path); break;
    }
    return $image;
  }

  private function get_file_path($file_id){
    $file_array = $this->bot->sendRequest('getFile', ['file_id' => $file_id]);
    return FILE_URL . $file_array['result']['file_path'];
  }

  private function add_watermark($image, $wm_image, $size, $coefficient = 1){
    $wm =  imagescale($wm_image, $size/ (2 * $coefficient));
    $sx = imagesx($wm);
    $sy = imagesy($wm);
    imagecopy($image, $wm, imagesx($image) - $sx - MARGE, imagesy($image) - $sy - MARGE, 0, 0, imagesx($wm), imagesy($wm));
    return $this->save_image($image, 'wm');
  }

  private function add_my_watermark($file_id){
    $main_image_path = $this->get_file_path($this->main_photo_id);
    $main_image = $this->get_image($main_image_path);
    $main_image_size = getimagesize($main_image_path)[0];
    return $this->add_watermark($main_image, $this->image, (int) $main_image_size, 3);
  } 

  private function create_text_watermark($text, $background_path){
    $text_wm = imagescale(imagecreatefrompng($background_path), 10 * strlen($text), 30);
    $color= imageColorAllocate($text_wm, 255, 255, 255);
    $px = (imageSX($text_wm) - 9 * strlen($text)) /2;
    $py = imageSY($text_wm) /2 - 9;
    imageString($text_wm, 5, $px, $py, $text, $color);
    return $text_wm;
  }

  private function change_pixel_color($image, $color, $x, $y, $symbol) {
    $color_pixel = imagecolorat ($image, $x, $y);
    $color_pixel_RGB = imagecolorsforindex ($image, $color_pixel);
    $color_pixel_RGB[$color] = ord ($symbol);
    $color_new_pixel = imagecolorclosest($image, $color_pixel_RGB[red], $color_pixel_RGB[green], $color_pixel_RGB[blue]);
    return imagesetpixel ($image, $x, $y, $color_new_pixel);
  }

  private function add_invisible_watermark($image, $text_wm, $size_array){
    $width = $size_array[0];
    $height = $size_array[1];
    $length = strlen($text_wm);
    $this->change_pixel_color($image, 'blue', 0, 0, '*');
    $this->change_pixel_color($image, 'red', 1, 0, '*');
    $this->change_pixel_color($image, 'green', 2, 0, '*');

    $x = 3;
    $y = 0;
    while ($length--) {
      $test = $this->change_pixel_color($image, 'blue', $x, $y, $text_wm[$length]);
      $x += 50; 
      if ($x > $width) {$x = 0; $y++;}
    }
    $color_new_pixel = imagecolorclosest ($image, 1, $color_pixel_RGB[green], 1);
    imagesetpixel ($image, $x, $y, $color_new_pixel);
    return $this->save_image($image, 'invisible_wm');
    
  }

  private function get_pixel_color($image, $color, $x, $y) {
    $color_pixel = imagecolorat ($image, $x, $y);
    $color_pixel_RGB = imagecolorsforindex ($image, $color_pixel);
    return $color_pixel_RGB[$color];
  }

  private function get_invisible_watermark($image, $size_array){
    $width = $size_array[0];
    $height = $size_array[1];
    $x = 3;
    $y = 0;
    $check_wm = chr($this->get_pixel_color($image, 'blue', 0, 0)) . chr($this->get_pixel_color($image, 'red', 1, 0)) . chr($this->get_pixel_color($image, 'green', 2, 0));
    if ($check_wm =='***'){
      $text = "";
      while ($this->get_pixel_color($image, 'red', $x, $y) != 1) {
        $text .= chr($this->get_pixel_color($image, 'blue', $x, $y));
        $x += 50;
        if ($x > $width) {$x=0; $y++;}
      } 
      return strrev($text);
    }
    else {
      return false;
    }

  }

  private function save_image($image, $mark){
    $this->name = $mark . $this->bot->chat_id . $this->id . '.png';
    return imagepng($image, $this->name);
  }
  
  public function __destruct() {
    unlink($this->name);
  }

}

$genie_bot = new Bot();

?>
