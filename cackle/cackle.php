<?php

class CackleApi
{
    protected $db;

    /**
     * Параметры подключения к базе данных
     * @var string
     */
    protected $dbHost = 'localhost';
    protected $dbUser = 'root';
    protected $dbPassword = '';
    protected $dbName = 'joomla';
    protected $dbPrefix = '';

    /**
     * Параметры Cackle приложения
     * @var string
     */
    protected $siteId = '{ВАШ SITE-ID}';
    protected $accountApiKey = '{ВАШ ACCOUNT-API-KEY}';
    protected $siteApiKey = '{ВАШ SITE-API-KEY}';
    /**
     * Время задержки после запроса, требуемое Cackle API
     * @var int
     */
    protected $waitTime = 5;
    protected $cli = false;

    public function __construct()
    {
        $this->cli = php_sapi_name() == "cli";
        $this->db = new mysqli($this->dbHost, $this->dbUser, $this->dbPassword, $this->dbName);
        $this->db->query("SET NAMES 'utf8'");
        $this->db->query("SET CHARACTER SET 'utf8'");
        $this->db->query("SET SESSION collation_connection = 'utf8_general_ci'");
        $this->checkInstall();
    }

    /**
     * Обертка запроса Mysqli::query
     * @param $sql
     * @return bool|mysqli_result
     */
    protected function query($sql)
    {
        $result = $this->db->query($sql);
        if (!$result) {
            $this->setLog($this->db->error . "\n" . $sql);
            return null;
        } else return $result;
    }

    /**
     * Получение последнего идентификатора комментария
     * @return int
     */
    public function getLastModified()
    {
        $query = $this->query("SELECT MAX(modified) as modified FROM {$this->dbPrefix}cackle_comments");
        $result = $query->fetch_assoc();
        return $result['modified'] ? $result['modified'] : 0;
    }

    /**
     * Синхронизация комментариев с cackle сервером
     */
    public function sync()
    {
        $lastModified = $this->getLastModified();
        $comments = $this->getComments($lastModified);
        foreach ($comments as $comment) {
            $this->addComment($comment);
        }
    }

    /**
     * Добавление комментария в базу. Изменение, в случае обновления статуса
     * @param $item
     */
    public function addComment($item)
    {
        if (!$item['id']) return;
        $comment_id = $item['id'];
        $parent_id = isset($item['parentId']) ? $item['parentId'] : 'NULL';
        $post_id = $item['chan']['channel'];
        $url = isset($item['chan']['url']) ? $item['chan']['url'] : '';
        $message = isset($item['message']) ? (string)$item['message'] : '';
        $status = $item['status'] == 'approved' ? 1 : 0;
        $user_agent = 'Cackle:' . $item['id'];
        $author_name = isset($item['author']['name']) ? $item['author']['name'] : '';
        $author_email = isset($item['author']['email']) ? $item['author']['email'] : '';
        $author_avatar = isset($item['author']['avatar']) ? $item['author']['avatar'] : '';
        $author_www = isset($item['author']['www']) ? $item['author']['www'] : '';
        $author_provider = isset($item['author']['provider']) ? $item['author']['provider'] : '';
        $anonym_name = isset($item['anonym']['name']) ? $item['anonym']['name'] : '';
        $anonym_email = isset($item['anonym']['email']) ? $item['anonym']['email'] : '';
        $created = strftime("%Y-%m-%d %H:%M:%S", $item['created'] / 1000);
        $modified = $item['modified'];
        $ip = $item['ip'];

        $sql = "INSERT INTO `{$this->dbPrefix}cackle_comments` 
        (`comment_id`, 
        `parent_id`, 
        `post_id`, 
        `url`, 
        `message`, 
        `status`, 
        `user_agent`, 
        `ip`, 
        `author_name`, 
        `author_email`, 
        `author_avatar`, 
        `author_www`, 
        `author_provider`, 
        `anonym_name`, 
        `anonym_email`, 
        `created`,
        `modified`) 
        VALUES ({$comment_id}, {$parent_id}, '" . $this->db->real_escape_string($post_id) . "',
         '" . $this->db->real_escape_string($url) . "', 
         '" . $this->db->real_escape_string($message) . "', {$status}, 
         '" . $this->db->real_escape_string($user_agent) . "', '{$ip}', 
         '" . $this->db->real_escape_string($author_name) . "', 
         '" . $this->db->real_escape_string($author_email) . "', 
         '" . $this->db->real_escape_string($author_avatar) . "', 
         '" . $this->db->real_escape_string($author_www) . "', 
         '" . $this->db->real_escape_string($author_provider) . "',
         '" . $this->db->real_escape_string($anonym_name) . "', 
         '" . $this->db->real_escape_string($anonym_email) . "', '{$created}','{$modified}') 
         ON DUPLICATE KEY UPDATE status={$status}, modified='{$modified}';";
        echo "add comment {$comment_id} \n";
        $this->query($sql);
    }

    /**
     * Получение всех последних комментариев
     */
    protected function getComments($lastModified)
    {
        $data = array(
            'url'    => 'http://cackle.me/api/3.0/comment/list.json',
            'params' => array(
                'modified' => $lastModified
            ),
            'key'    => 'comments'
        );
        return $this->sendToApi($data);

    }

    /**
     * Генерация виджета комментариев
     * @param $channel
     * @return string
     */
    public function getWidget($channel)
    {
        $comments_html = '';
        $comments = $this->getCommentsByChannel($channel);
        foreach ($comments as $comment) {
            $comments_html .= '<li  id="cackle-comment-' . $comment['comment_id'] . '">
<div id="cackle-comment-header-' . $comment['comment_id'] . '" class="cackle-comment-header">
	<cite id="cackle-cite-' . $comment['comment_id'] . '">'
                . ($comment['author_name'] ? '<a id="cackle-author-user-' . $comment['comment_id'] . '" href="#" target="_blank" rel="nofollow">' . $comment['author_name'] . '</a>' : '<span id="cackle-author-user-' . $comment['comment_id'] . '">' . $comment['anonym_name'] . '</span>') . '
	</cite>
</div>
<div id="cackle-comment-body-' . $comment['comment_id'] . '" class="cackle-comment-body">
	<div id="cackle-comment-message-' . $comment['comment_id'] . '" class="cackle-comment-message">
		' . $comment['message'] . '
	</div>
</div>
</li>';
        }

        $result = <<<HTML
                <div id="mc-container">
                <div id="mc-content">
                    <ul id="cackle-comments">
                    $comments_html
                    </ul>
                </div>
                </div>
				<script type="text/javascript">
				cackle_widget = window.cackle_widget || [];
                cackle_widget.push({widget: 'Comment', id: '{$this->siteId}', channel: "{$channel}" });
				document.getElementById('mc-container').innerHTML = '';
                (function() {
                  var mc = document.createElement('script');
                  mc.type = 'text/javascript';
                  mc.async = true;
                  mc.src = ('https:' == document.location.protocol ? 'https' : 'http') + '://cackle.me/widget.js';
                  var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
                })();
				</script>
HTML;
        return $result;
    }

    /**
     * Получение всех комментариев на канале
     * @param $channel
     */
    protected function getCommentsByChannel($channel)
    {
        $sql = "SELECT * FROM `{$this->dbPrefix}cackle_comments` WHERE `post_id`= '"
            . $this->db->real_escape_string($channel) . "' AND status = 1";
        $query = $this->query($sql);
        $rows = array();
        while ($row = $query->fetch_assoc()) $rows[] = $row;
        return $rows;
    }

    /**
     * Подготовка запроса к api и получение результата
     * @param $data
     */
    protected function sendToApi($data)
    {
        //debug
        //return json_decode(file_get_contents('allcomments.json'), true);
        $url = $data['url'];
        $params = $data['params'];
        $params['id'] = $this->siteId;
        $params['siteApiKey'] = $this->siteApiKey;
        $params['accountApiKey'] = $this->accountApiKey;
        $params['page'] = 0;

        $result = array();
        $next = true;
        do {
            $res = file_get_contents($url . '?' . http_build_query($params));
            if ($res) $res = json_decode($res, true);
            if (!is_array($res)) {
                $this->setLog('Полученнный ответ от API не является массивом. Выход');
                exit('error response');
            }
            $res = $res[$data['key']];
            foreach ($res as $item) $result[] = $item;
            if (count($res) < 100) $next = false;
            $params['page']++;
            //обязательное условие API спать 5 сек после запроса
            echo "response array count " . count($res) . "\n";
            if (count($res) == 100) {
                echo "sleep {$this->waitTime}\n";
                echo "next page {$params['page']}\n";
                sleep($this->waitTime);
            }
        } while ($next);
        //debug
        //file_put_contents(dirname(__FILE__) . '/allcomments.json', json_encode($result));
        return $result;
    }

    protected function setLog($message)
    {
        file_put_contents(dirname(__FILE__) . '/errors.txt', date('d.m.Y H:i:s') . ' ' . $message . "\n", FILE_APPEND);
    }

    /**
     * Проверка установки таблиц скрипта
     */
    protected function checkInstall()
    {
        $query = $this->db->query("SELECT * FROM information_schema.tables WHERE table_schema = '{$this->dbName}' AND table_name = '{$this->dbPrefix}cackle_comments' LIMIT 1");
        if ($query->num_rows == 0) {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->dbPrefix}cackle_comments` (
  `comment_id` bigint(20) NOT NULL,
  `parent_id` bigint(20) DEFAULT NULL,
  `post_id` varchar(500) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `message` text,
  `status` varchar(11) DEFAULT NULL,
  `user_agent` varchar(200) NOT NULL,
  `ip` varchar(39) DEFAULT NULL,
  `author_name` varchar(60) DEFAULT NULL,
  `author_email` varchar(100) DEFAULT NULL,
  `author_avatar` varchar(200) DEFAULT NULL,
  `author_www` varchar(200) DEFAULT NULL,
  `author_provider` varchar(32) DEFAULT NULL,
  `anonym_name` varchar(60) DEFAULT NULL,
  `anonym_email` varchar(100) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`comment_id`),
  UNIQUE KEY `user_agent` (`user_agent`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
            $this->db->query($sql);
        }
    }
}