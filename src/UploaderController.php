<?php

/*
 * This file is part of Gitamin.
 *
 * Copyright (C) 2015-2016 The Gitamin Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phecho\Uploader;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Routing\Controller;
use Intervention\Image\Facades\Image;

class UploaderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function indexAction(Request $request, $type = 'default')
    {
        // Get the configuration
        $config = Config::get('uploader');

        $configType = Config::get('uploader.types.'.$type);

        if (! $configType) {
            return ['error' => 'invalid_type'];
        }

        // Check if file is uploaded
        if (! $request->hasFile($config['file_field'])) {
            return ['error' => 'file_not_exists'];
        }

        $file = $request->file($config['file_field']);

        // get file size in Bytes
        $file_size = $file->getSize();

        // Check the file size
        if ($file_size > $config['max_size'] * 1024 || (isset($configType['max_size']) && $file_size > $configType['max_size'] * 1024)) {
            return ['error' => 'limit_size'];
        }

        // get the extension
        $ext = strtolower($file->getClientOriginalExtension());

        // checking file format
        $format = $this->getFileFormat($ext);

        // TODO: check file format
        if (isset($configType['format']) && ! in_array($format, explode('|', $configType['format']))) {
            return ['error' => 'invalid_format'];
        }

        // saving file
        $filename = date('U').str_random(10);
        $file->move(public_path().'/'.$config['dir'].'/'.$type, $filename.'.'.$ext);

        $file_path = $config['dir'].'/'.$type.'/'.$filename.'.'.$ext;

        if ($format == 'image' && isset($config['types'][$type]['image']) && count($config['types'][$type]['image'])) {
            $img = Image::make(public_path().'/'.$file_path);

            foreach ($config['types'][$type]['image'] as $task => $params) {
                switch ($task) {
                    case 'resize':
                        $img->resize($params[0], $params[1]);
                        break;
                    case 'fit':
                        $img->fit($params[0], $params[1]);
                        break;
                    case 'crop':
                        $img->crop($params[0], $params[1]);
                        break;
                    case 'thumbs':
                        $img->save();

                        foreach ($params as $name => $sizes) {
                            $img->backup();

                            $thumb_path = $config['dir'].'/'.$filename.'-'.$name.'.'.$ext;

                            $img->fit($sizes[0], $sizes[1])->save($thumb_path);

                            $img->reset();
                        }
                        break;
                }
            }

            $img->save();
        }

        return [
            'data' => [
                'original' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file_size,
                ],
                'ext' => $ext,
                'format' => $format,
                // 'image' => [
                //     'size' =>$img->getSize(),
                // ],
                'name' => $filename,
                'path' => $file_path,
            ],
        ];
    }

    protected function getFileFormat($ext)
    {
        if (preg_match('/(jpg|jpeg|gif|png)/', $ext)) {
            return 'image';
        } elseif (preg_match('/(mp3|wav|ogg)/', $ext)) {
            return 'audio';
        } elseif (preg_match('/(mp4|wmv|flv)/', $ext)) {
            return 'video';
        } elseif (preg_match('/txt/', $ext)) {
            return 'text';
        }

        return 'other';
    }
}
