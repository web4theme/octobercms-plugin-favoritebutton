<?php namespace BABA\Favoritebutton\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Favorites extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['baba.favoritebutton.manage_favorites'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('BABA.Favoritebutton', 'favorites', 'favorites');
    }

    public function listExtendQuery($query)
    {
        $query->with(['group', 'tags']);
    }
}
