# Pages — шаблоны и страницы

`IFMiniLib\Pages` — класс для рендеринга HTML-страниц из PHP-шаблонов.

## Инициализация

```php
use IFMiniLib\Pages;

$pages = new Pages(App::one());
```

## Отображение страницы

```php
// show($page, $layout, $vars)
$pages->show('index', 'main', [
    'pageTitle' => 'Главная',
    'user'      => $user,
]);
```

- `$page` — имя шаблона (файл `tpl/{page}.tpl.php`)
- `$layout` — имя лэйаута (папка `layout/{layout}/`)
- `$vars` — переменные, доступные в шаблоне

## Структура файлов

```
tpl/
└── index.tpl.php        # шаблон страницы

layout/
└── main/
    ├── header.php       # шапка (выводится перед контентом)
    └── footer.php       # футер (выводится после контента)
```

## Шаблон страницы (tpl/index.tpl.php)

Все переменные из `$vars` доступны напрямую через `extract()`:

```php
<!-- tpl/index.tpl.php -->
<h1><?= htmlspecialchars($pageTitle) ?></h1>

<?php if ($user): ?>
    <p>Привет, <?= htmlspecialchars($user->name) ?>!</p>
<?php endif; ?>
```

## Лэйаут

```php
<!-- layout/main/header.php -->
<!DOCTYPE html>
<html>
<head>
    <title><?= $pages->getTitle() ?></title>
    <?= $pages->getClientCss('top') ?>
</head>
<body>
```

```php
<!-- layout/main/footer.php -->
    <?= $pages->getClientJs('bottom') ?>
</body>
</html>
```

## Заголовок страницы

```php
$pages->setTitle('Статья: Как работает PHP');
$pages->setH1('Как работает PHP');

$pages->getTitle(); // 'Статья: Как работает PHP'
$pages->getH1();    // 'Как работает PHP'
// если title не задан — getTitle() вернёт H1
```

## JavaScript и CSS

```php
// Добавить ссылку на файл
$pages->addClientJs('jquery', '/js/jquery.min.js', 'top');
$pages->addClientCss('bootstrap', '/css/bootstrap.min.css', 'top');

// Добавить inline-код
$pages->addClientJs('init', 'document.addEventListener("DOMContentLoaded", () => { ... })', 'bottom');

// Вывод в шаблоне (layout)
echo $pages->getClientJs('top');    // <script src="...">
echo $pages->getClientCss('top');   // <link rel="stylesheet" ...>
echo $pages->getClientJs('bottom'); // в конце body
```

## Произвольные свойства

```php
// В Action
$pages->setProperty('breadcrumbs', [
    ['url' => '/', 'title' => 'Главная'],
    ['url' => '/blog/', 'title' => 'Блог'],
]);

// В шаблоне
$breadcrumbs = $pages->getProperty('breadcrumbs');
```

## Редирект

```php
$pages->redirect('/login/');            // на конкретный URL
$pages->redirect();                     // на $_REQUEST['page']
```

## Полный пример Action

```php
<?php
namespace MyApp\WebActions;

use MyApp\App;
use IFMiniLib\BaseAction;
use IFMiniLib\Pages;
use IFMiniLib\Request;
use IFMiniLib\DB;

class BlogAction extends BaseAction
{
    public function run()
    {
        $page  = max(1, (int) Request::one()->{'get.page'});
        $posts = DB::getAll([
            'model'    => ModelPosts::class,
            'filter'   => ['statusid' => 1],
            'sort'     => ['created' => 'DESC'],
            'pageSize' => 10,
            'page'     => $page,
        ]);

        $pages = new Pages(App::one());
        $pages->setTitle('Блог');
        $pages->addClientCss('blog', '/css/blog.css');

        return $pages->show('blog', 'main', [
            'posts'     => $posts,
            'pageNum'   => $page,
        ]);
    }
}
```
