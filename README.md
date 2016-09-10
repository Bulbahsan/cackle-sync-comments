# cackle-sync-comments
Синхронизация комментариев Cackle с локальной базой.

Скрипт выводит на страницу блок с комментариями из базы, которые будут успешно проиндексированы поисковиками и сам виджет Cackle с комментариями.

###НАСТРОЙКА СКРИПТА
Все настройки производятся в файле **cackle.php**:

1. Измените параметры подключения к базе:

    protected $dbHost = 'localhost';
    
    protected $dbUser = 'root';
    
    protected $dbPassword = '';
    
    protected $dbName = 'test';
    
    protected $dbPrefix = '';
    
2. Измените параметры Cackle приложения:

    protected $siteId = 'ВАШ-SITE-ID';
    
    protected $accountApiKey = 'ВАШ-ACCOUNT-API-KEY';
    
    protected $siteApiKey = 'ВАШ-SITE-API-KEY';

###ЗАПУСК СКРИПТА

Настройте cron-задачу на запуск в определенное время (1 раз в час или др.), для получения новых комментариев. Например:

`cd /var/www/site/cackle/ && php cron.php`

Для отображения виджета с комментариями Cackle, в нужном месте страницы подключите файл **widget.php**. Например:

`require $_SERVER['DOCUMENT_ROOT'].'/cackle/widget.php';`
