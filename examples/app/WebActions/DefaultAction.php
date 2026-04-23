<?php

namespace Example\WebActions;

use Example\App;
use IFMiniLib\ActiveUser;
use IFMiniLib\Response;
use IFMiniLib\Pages;

class DefaultAction extends \IFMiniLib\BaseAction
{
    public function run()
    {
        if (! ActiveUser::one()->id) {
            //Response::one()->redirect('/login/', 302);
            echo "You are not loged in!";
        }

        $vars = [
            'pageTitle' => 'Index Page',
            'activeSection' => '',
            'user' => [
                'id' => ActiveUser::one()->id,
                'name' => ActiveUser::one()->name
            ],
            'menu' => ['main' => 'Main Page']
        ];
        return (new Pages(App::one()))->show('index', 'main', $vars);
    }
}
