
<?php
define('ROOT', $_SERVER['DOCUMENT_ROOT']);
require ROOT.'/vendor/autoload.php';
require ROOT.'/functions.php';

$configs = include(ROOT.'/config.php');

use Intervention\Image\ImageManager as Image;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

$uri = $_SERVER['REQUEST_URI'];// /sudo-vn/images/2020/07/w300/logo-sudo.jpg.webp

//Kiểm tra các url cần loại bỏ
if ($uri == '/')
    exit();

$parse_uri = pathinfo($uri);

$dirname = $parse_uri['dirname'] ?? null;
$basename = $parse_uri['basename'] ?? null;
$filename = $parse_uri['filename'] ?? null;
$extension = $parse_uri['extension'] ?? null;
$dirname = substr($dirname, 1);//cắt dấu / đầu tiên đi

//Kiểm tra extension
if(!$extension || !in_array(strtolower($extension),$configs['allowed_ext'])) {
    exit('Extension not allowed');
}

if($extension == 'webp') {
    $extension_real = substr($filename, strrpos($filename,'.')+1);
    $filename_real = substr($filename, 0, strrpos($filename,'.'));
}else {
    $extension_real = $extension;
    $filename_real = $filename;
}

//Kiểm tra lại extension_real
if(!$extension_real || !in_array(strtolower($extension_real),$configs['allowed_ext'])) {
    exit('Extension not allowed 2');
}

$resize_path = substr($dirname, strrpos($dirname,'/')+1);

// article
if(strpos($resize_path,'article') !== false) {
    try {
        $client = new S3Client($configs['server']);
        //Kiểm tra tồn tại

        $dirname = str_replace('/article', '', $dirname);
        $file_real = $dirname.'/'.$filename_real.'.'.$extension_real;
        if(!$client->doesObjectExist($configs['bucket'], $file_real)) {
            exit('Object note exist');
        }else {
            $image_real = $client->getObject([
                'Bucket' => $configs['bucket'],
                'Key' => $file_real,
            ]);
            $manager = new Image();
            $image = $manager->make($image_real['Body']);

            $width = $image->width();
            $height = $image->height();

            $r = $width/$height;
            if ($r <= 1.16666){
                $new_height =  $width;
            } elseif ($r < 1.55555) {
                $new_height =  $width*3/4;
            } else {
                $new_height =  $width*9/16;
            }

            if($extension == 'webp') {
                $image_article = $image->encode('webp')->resize($width, $new_height)->stream('webp',100);
                $image_article_name = $dirname.'/article/'.$filename_real.'.'.$extension_real.'.webp';
                $mime = 'image/webp';
            }else {
                $image_article = $image->resize($width, $new_height)->stream($extension_real,100);
                $image_article_name = $dirname.'/article/'.$filename_real.'.'.$extension_real;
                $mime = $image->mime();
            }

            $client->putObject([
                'Bucket' => $configs['bucket'],
                'Key'    => $image_article_name,
                'Body'   => $image_article->__toString(),
                'ContentType' => $mime,
                'ACL'    => 'public-read'
            ]);
            header("Content-Type: {$mime}");
            echo $image_article->__toString();
            die;
        }
    }catch (S3Exception $e) {
        exit('Exception s3');
        //echo $e->getMessage();
        //dump($e);
    }
}
//Nếu không phải resize
if(strpos($resize_path,'w') !== 0) {
    //Nếu là convert ảnh gốc sang ảnh webp
    if ($extension == 'webp') {
        try {
            $client = new S3Client($configs['server']);
            //Kiểm tra tồn tại
            $file_real = $dirname.'/'.$filename_real.'.'.$extension_real;
            if(!$client->doesObjectExist($configs['bucket'], $file_real)) {
                exit('Object note exist');
            }else {

                $image_real = $client->getObject([
                    'Bucket' => $configs['bucket'],
                    'Key' => $file_real,
                ]);
                $manager = new Image();
                $image = $manager->make($image_real['Body']);
                $image_convert = $image->encode('webp')->stream('webp',100);
                $image_convert_name = $dirname.'/'.$filename_real.'.'.$extension_real.'.webp';
                $mime = 'image/webp';
                $client->putObject([
                    'Bucket' => $configs['bucket'],
                    'Key'    => $image_convert_name,
                    'Body'   => $image_convert->__toString(),
                    'ContentType' => $mime,
                    'ACL'    => 'public-read'
                ]);
                header("Content-Type: {$mime}");
                echo $image_convert->__toString();
                die;
            }
        }catch (S3Exception $e) {
            exit('Exception s3');
            //echo $e->getMessage();
            //dump($e);
        }
    }
    //Không phải thì quit
    exit('Not resize');
}

if(strpos($dirname,'/') !== false) {
    $path_real = substr($dirname, 0, strrpos($dirname,'/'));// sudo-vn/images/2020/07
}else {
    $path_real = $dirname;// test
}

$file_real = $path_real.'/'.$filename_real.'.'.$extension_real;
$file_real = urldecode($file_real);
$resize_width = str_replace('w', '', $resize_path);
//Kiểm tra xem kích thước resize có cho phép ko
if (!in_array($resize_width,$configs['allowed_width'])) {
    exit('Resize with not avaible');
}


try {
    $client = new S3Client($configs['server']);
    //Kiểm tra tồn tại
    if(!$client->doesObjectExist($configs['bucket'], $file_real)) {
        exit('Object not exist 2');
    }else {
        $image_real = $client->getObject([
            'Bucket' => $configs['bucket'],
            'Key' => $file_real,
        ]);
        $manager = new Image();
        $image = $manager->make($image_real['Body']);
        if($extension == 'webp') {
            $image_resize = $image->encode('webp')->widen($resize_width)->stream('webp',100);
            $image_resize_name = $dirname.'/'.$filename_real.'.'.$extension_real.'.webp';
            $mime = 'image/webp';
        }else {
            $image_resize = $image->widen($resize_width)->stream($extension_real,100);
            $image_resize_name = $dirname.'/'.$filename_real.'.'.$extension_real;
            $mime = $image->mime();
        }

        $client->putObject([
            'Bucket' => $configs['bucket'],
            'Key'    => $image_resize_name,
            'Body'   => $image_resize->__toString(),
            'ContentType' => $mime,
            'ACL'    => 'public-read'
        ]);
        header("Content-Type: {$mime}");
        echo $image_resize->__toString();
        die;
    }
}catch (S3Exception $e) {
    exit('Exception s3 2');
    //echo $e->getMessage();
    //dump($e);
}