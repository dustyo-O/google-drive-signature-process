<?php
/**
 * Created by PhpStorm.
 * User: dusty
 * Date: 16.09.16
 * Time: 9:57
 */
define("PDF2_JPG_IMAGE_RESOLUTION",150); // разрешение jpg
define("PDF2_JPG_IMAGE_QUALITY",80); // качество изображения


$pdf = json_decode($_POST['pdf']);
$sign = json_decode($_POST['jpg']);
$folder_id = $_POST['folder'];
$token = $_POST['token'];

//$r = new HttpRequest($url, HttpRequest::METH_GET);
//$r->setHeaders("Authorization","Bearer ".$token);
$ch = curl_init();

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer '.$token
));

$pdf_filepath = "./runtime/{$pdf->name}";
$pdf_google_url = "https://www.googleapis.com/drive/v3/files/{$pdf->id}?alt=media";

curl_setopt($ch, CURLOPT_URL, $pdf_google_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 0);

// grab URL
$pdf_content = curl_exec($ch);

// close cURL resource, and free up system resources
curl_close($ch);

if (file_put_contents($pdf_filepath,$pdf_content) === FALSE) // TODO еще надо проверить, что pdf - реально PDF а не json с ошибкой
{
    echo("{\"answer\": \"error\", \"type\": \"error\", \"message\": \"Не удалось скачать pdf\"}");
    die();
}

$ch = curl_init();

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer '.$token
));

$sign_filepath = "./runtime/{$sign->name}";
$sign_google_url = "https://www.googleapis.com/drive/v3/files/{$sign->id}?alt=media";

curl_setopt($ch, CURLOPT_URL, $sign_google_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 0);

// grab URL
$sign_content = curl_exec($ch);

// close cURL resource, and free up system resources
curl_close($ch);

if (file_put_contents($sign_filepath,$sign_content) === FALSE)
{
    echo("{\"answer\": \"error\", \"type\": \"error\", \"message\": \"Не удалось скачать подпись\"}");
    die();
}


// КОНВЕРТАЦИЯ


function rus2translit($string) {
    $converter = array(
        'а' => 'a',   'б' => 'b',   'в' => 'v',
        'г' => 'g',   'д' => 'd',   'е' => 'e',
        'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
        'и' => 'i',   'й' => 'y',   'к' => 'k',
        'л' => 'l',   'м' => 'm',   'н' => 'n',
        'о' => 'o',   'п' => 'p',   'р' => 'r',
        'с' => 's',   'т' => 't',   'у' => 'u',
        'ф' => 'f',   'х' => 'h',   'ц' => 'c',
        'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
        'ь' => '_',  'ы' => 'y',   'ъ' => '_',
        'э' => 'e',   'ю' => 'yu',  'я' => 'ya',

        'А' => 'A',   'Б' => 'B',   'В' => 'V',
        'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
        'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
        'И' => 'I',   'Й' => 'Y',   'К' => 'K',
        'Л' => 'L',   'М' => 'M',   'Н' => 'N',
        'О' => 'O',   'П' => 'P',   'Р' => 'R',
        'С' => 'S',   'Т' => 'T',   'У' => 'U',
        'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
        'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
        'Ь' => '_',  'Ы' => 'Y',   'Ъ' => '_',
        'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
    );
    return strtr($string, $converter);
}

if (class_exists("Imagick"))
{
    $im = new Imagick();
}
else
{
    echo <<<JSON
{"answer": "error", "message": "Imagick не установлен, конвертация невозможна! Свяжитесь с администратором сервера.", "type": "fatal"}
JSON;
    die();
}



$pie = pathinfo($pdf->name,PATHINFO_FILENAME);

$fold_title = $pie[0];

$relfolder = $fold_title.'_'.time();
$tmpfolder = './runtime/'.$relfolder;
$tmpfolder = str_replace('//','/',$tmpfolder);

if (!mkdir($tmpfolder))
{
    echo <<<JSON
{"answer": "error", "message": "Невозможно создать временную папку для конвертации, проверьте права", "type": "fatal"}
JSON;
    die();
}

if (!file_exists($pdf_filepath))
{
    echo <<<JSON
{"answer": "error", "message": "Не удалось считать файл {$pdf->name}, возможно файл не скачался? Если ошибка повторяется, проверьте место на диске и декодер для pdf", "type": "error"}
JSON;
    die();

}

$jpg_name = $tmpfolder.'/'.rus2translit($fold_title);



$im->setResolution(PDF2_JPG_IMAGE_RESOLUTION,PDF2_JPG_IMAGE_RESOLUTION);
if (!$im->readimage($pdf_filepath))
{
    echo <<<JSON
{"answer": "error", "message": "Не удалось считать файл {$pdf->name}, возможно файл не скачался? Если ошибка повторяется, проверьте место на диске и декодер для pdf", "type": "error"}
JSON;
    die();

}
$num_pages = $im->getNumberImages();

// Compress Image Quality
$im->setImageCompressionQuality(PDF2_JPG_IMAGE_QUALITY);
$answer = 'ok';
$answer_type = 'success';
$answer_message = "";
$unconverted = 0;

    $i = 0; // одну страницу

    $im->setIteratorIndex($i);

    // Set image format
    $im->setImageFormat('jpeg');

    $n = $i+1;

    if (!$im->writeImage($jpg_name.".jpg"))
    {
        $unconverted++;
        $answer = "error";
        $answer_type = "warning";
        $answer_message = "Страниц завершено с ошибками: {$unconverted}. Если ошибка повторяется, проверьте место на диске";
    }


$im->clear();
$im->destroy();

$converted_filepath = $jpg_name.".jpg";

@unlink($pdf_filepath);

// ДОБАВЛЕНИЕ ПОДПИСИ НА РЕЗУЛЬТАТ

$imagick = new \Imagick($sign_filepath);

list ($w,$h) = getimagesize($sign_filepath);

$w10 = floor($w/10);
$h10 = floor($h/3.5);

$cropped_width = $w - 2 * $w10;
$cropped_height = $h - 2 * $h10;

$imagick->cropImage($cropped_width, $cropped_height, $w10, $h10);

$imagick->resizeImage(400,115,Imagick::FILTER_LANCZOS, 1, TRUE);
$imagick->setImageColorSpace(Imagick::COLORSPACE_GRAY);
$imagick->modulateImage(200,0,200);
$imagick->whiteThresholdImage('grey');

$imagick->writeImage($sign_filepath);

$doc = imagecreatefromjpeg($converted_filepath);
$sign = imagecreatefromjpeg($sign_filepath);
list ($w,$h) = getimagesize($sign_filepath);

imagecopy($doc,$sign,765, 1268, 0, 0, $w, $h);

imagejpeg($doc,$converted_filepath, 100);


// UPLOAD

$file = $converted_filepath;

$content_type = "image/jpeg";

$contents = file_get_contents($file);
$size = filesize($file);

$pdf_pie = explode('.',$pdf->name);
$title = $pdf_pie[0]."jpg";


$json = "
{
    \"name\": \"{$title}\",
    \"parents\": [\"{$folder_id}\"]
}
";


$bound = "google_drive_bound";

$multipart = "
--{$bound}
Content-Type: application/json; charset=UTF-8

{$json}

--{$bound}
Content-Type: {$content_type}

{$contents}
--{$bound}--
";

$headers = array
(
    "Content-Type: multipart/related; boundary=\"{$bound}\"",
    "Content-Length: ".strlen($multipart),
    "Authorization: Bearer ".$token
);


$url = "https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$return = curl_exec($ch);
$status_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);

echo "{\"answer\":\"ok\", \"status_code\": \"{$status_code}\"}";

@unlink($file);
@unlink($sign_filepath);
@unlink($tmpfolder);




