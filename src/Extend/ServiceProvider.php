<?php

namespace Dcat\Admin\Extend;

use Dcat\Admin\Admin;
use Dcat\Admin\Support\ComposerProperty;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Symfony\Component\Console\Output\NullOutput;

abstract class ServiceProvider extends LaravelServiceProvider
{
    /**
     * @var ComposerProperty
     */
    protected $composerProperty;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $js = [];

    /**
     * @var array
     */
    protected $css = [];

    /**
     * @var array
     */
    protected $menu = [];

    /**
     * @var array
     */
    protected $permission = [];

    /**
     * @var array
     */
    protected $menuValidationRules = [
        'title' => 'required',
        'path'  => 'required',
        'icon'  => 'required',
    ];

    /**
     * @var array
     */
    protected $permissionValidationRules = [
        'name'  => 'required',
        'slug'  => 'required',
        'path'  => 'required',
    ];

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    public $output;

    public function __construct($app)
    {
        parent::__construct($app);

        $this->output = new NullOutput();
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        if ($views = $this->views()) {
            $this->loadViewsFrom($views, $this->name());
        }

        if ($lang = $this->lang()) {
            $this->loadTranslationsFrom($lang, $this->name());
        }

        if ($migrations = $this->migrations()) {
            $this->loadMigrationsFrom($migrations);
        }

        if ($routes = $this->routes()) {
            return $this->registerRoutes($routes);
        }

        $this->registerAssets();
    }

    /**
     * 获取扩展名称.
     *
     * @return string
     */
    final public function name()
    {
        return $this->name ?: ($this->name = str_replace('/', '.', $this->composerProperty->name));
    }

    /**
     * 获取扩展包路径.
     *
     * @param string $path
     *
     * @return string
     *
     * @throws \ReflectionException
     */
    public function path(?string $path = null)
    {
        if (! $this->path) {
            $this->path = realpath(dirname((new \ReflectionClass(static::class))->getFileName()).'/..');

            if (! is_dir($this->path)) {
                throw new \Exception("The {$this->path} is not a directory.");
            }
        }

        $path = ltrim($path, '/');

        return $path ? $this->path.'/'.$path : $this->path;
    }

    /**
     * 判断扩展是否启用.
     *
     * @return bool
     */
    final public function enabled()
    {
        return in_array($this->name(), Admin::setting()->getExtensionsEnabled(), true);
    }

    /**
     * 判断扩展是否禁用.
     *
     * @return bool
     */
    final public function disabled()
    {
        return ! $this->enabled();
    }

    /**
     * 获取配置.
     *
     * @param string $key
     * @param null   $default
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    final public function config($key = null, $default = null)
    {



    }

    /**
     * 导入扩展.
     */
    public function import()
    {
        $this->importMenus();
        $this->importPermissions();
    }

    /**
     * 卸载扩展.
     */
    public function uninstall()
    {
    }

    /**
     * 注册路由.
     *
     * @param $callback
     */
    public function registerRoutes($callback)
    {
        Admin::app()->routes(function ($router) use ($callback) {
            $attributes = array_merge(
                [
                    'prefix'     => config('admin.route.prefix'),
                    'middleware' => config('admin.route.middleware'),
                ]
            );

            $router->group($attributes, $callback);
        });
    }

    /**
     * 获取静态资源路径.
     *
     * @return string
     */
    public function assets()
    {
        return $this->path('resources/assets');
    }

    /**
     * 获取视图路径.
     *
     * @return string
     */
    public function views()
    {
        return $this->path('resources/views');
    }

    /**
     * 获取数据迁移文件路径.
     *
     * @return string
     */
    public function migrations()
    {
        return $this->path('database/migrations');
    }

    /**
     * 获取语言包路径.
     *
     * @return string
     */
    public function lang()
    {
        return $this->path('resources/lang');
    }

    /**
     * 获取路由地址.
     *
     * @return string
     *
     * @throws \ReflectionException
     */
    public function routes()
    {
        $path = $this->path('src/Http/routes.php');

        return is_file($path) ? $path : null;
    }

    /**
     * 获取菜单.
     *
     * @return array
     */
    public function menu()
    {
        return $this->menu;
    }

    /**
     * @return array
     */
    public function permission()
    {
        return $this->permission;
    }

    /**
     * @param ComposerProperty $composerProperty
     *
     * @return $this
     */
    public function withComposerProperty(ComposerProperty $composerProperty)
    {
        $this->composerProperty = $composerProperty;

        return $this;
    }

    /**
     * 导入菜单.
     *
     * @throws \Exception
     */
    protected function importMenus()
    {
        if (! ($menu = $this->menu()) || ! $this->validateMenu($menu)) {
            return;
        }

        extract($menu);

        if ($this->checkMenu($path)) {
            $this->output->writeln("<warn>Menu [$path] already exists!</warn>");
        } else {
            $this->createMenu($title, $path, $icon);
            $this->output->writeln('<info>Import extension menu succeeded!</info>');
        }
    }

    /**
     * 导入权限.
     *
     * @throws \Exception
     */
    protected function importPermissions()
    {
        if (! $this->config('admin.permission.enable')) {
            return;
        }

        if (! ($permission = $this->permission()) || ! $this->validatePermission($permission)) {
            return;
        }

        extract($permission);

        if ($this->checkPermission($slug)) {
            $this->output->writeln("<warn>Permission [$slug] already exists!</warn>");
        } else {
            $this->createPermission($name, $slug, $path);
            $this->output->writeln('<info>Import extension permission succeeded!</info>');
        }
    }

    /**
     * 注册别名.
     */
    protected function registerAssets()
    {
        if ($this->js || $this->css) {
            Admin::asset()->alias($this->name(), $this->js, $this->css);
        }
    }

    /**
     * 验证菜单.
     *
     * @param array $menu
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function validateMenu(array $menu)
    {
        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($menu, $this->menuValidationRules);

        if ($validator->passes()) {
            return true;
        }

        $message = "Invalid menu:\r\n".implode("\r\n", Arr::flatten($validator->errors()->messages()));

        $this->output->writeln("<error>{$message}</error>");
    }

    /**
     * @param $path
     *
     * @return bool
     */
    protected function checkMenu($path)
    {
        $menuModel = config('admin.database.menu_model');

        return $result = $menuModel::where('uri', $path)->exists();
    }

    /**
     * 验证权限.
     *
     * @param array $permission
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function validatePermission(array $permission)
    {
        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($permission, $this->permissionValidationRules);

        if ($validator->passes()) {
            return true;
        }

        $message = "Invalid permission:\r\n".implode("\r\n", Arr::flatten($validator->errors()->messages()));

        $this->output->writeln("<error>{$message}</error>");
    }

    /**
     * 创建菜单.
     *
     * @param string $title
     * @param string $uri
     * @param string $icon
     * @param int    $parentId
     */
    protected function createMenu($title, $uri, $icon = 'fa-bars', $parentId = 0)
    {
        $menuModel = config('admin.database.menu_model');

        $lastOrder = $menuModel::max('order');

        $menuModel::create([
            'parent_id' => $parentId,
            'order'     => $lastOrder + 1,
            'title'     => $title,
            'icon'      => $icon,
            'uri'       => $uri,
        ]);
    }

    /**
     * @param $slug
     *
     * @return bool
     */
    protected function checkPermission($slug)
    {
        $permissionModel = config('admin.database.permissions_model');

        return $permissionModel::where('slug', $slug)->exists();
    }

    /**
     * 创建权限.
     *
     * @param $name
     * @param $slug
     * @param $path
     */
    protected function createPermission($name, $slug, $path)
    {
        $permissionModel = config('admin.database.permissions_model');

        $permissionModel::create([
            'name'      => $name,
            'slug'      => $slug,
            'http_path' => trim($path, '/'),
        ]);
    }
}
