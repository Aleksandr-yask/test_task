<?php

abstract class Model
{
    abstract public function getCreatedAt(): string;
}

/**
 * Этот класс имеет сразу несколько проблем:
 * - Нарушение SRP - логика работы с бд и хранение данных
 * - Экземпляр mysqli создается внутри класса, что создает сильную связанность
 * - Креды для подключения хранятся в коде (проблема не только в том что креды становятся доступными для всех разработчиков, но и снижает возможности раширения и рефакторинга)
 * - Публичные свойства без типов данных, заполнение, типизацию этих свойств невозможно контролировать
 * - Нарушение приципа Лисков. Реализуемый метод createdAt отдает ошибку о неподдержке
 * - SQL инъекция в запросе
 * - Получение данных через звездочку не очень хорошая практика. В память выгружаются данные которые не используются
 */
class User extends Model
{
    private $db;

    public $id;
    public $username;
    public $email;
    public $role;

    public function __construct() {
        $this->db = new mysqli('localhost', 'username', 'password', 'database');
    }

    public function getCreatedAt(): string
    {
        throw new \Exception('Not supported');
    }

    public function findById($id): User {
        $query = "SELECT * FROM users where id = " . $id;
        $result = $this->db->query($query)->fetch_row();
        $user = new User();
        $user->role = $result['role'];
        $user->id = $result['id'];
        $user->username = $result['username'];
        $user->email = $result['email'];

        return $user;
    }

    // ...
}

/**
 * Ошибки те же что и в классе User, плюсом еще:
 * - В savePost происходит доменной логики завязка на http, что делает ее менее переиспользуемой
 * - Используется exit; что вредно. Невозможно корректно обработать результат выполнения
 * - К нарушению SRP прибавляется логирование, логирование нужно вынести в отдельный класс
 */
class Post
{
    private $db;

    public $id;
    public $text;
    public $type;
    public $created_at;

    public function __construct()
    {
        $this->db = new mysqli('localhost', 'username', 'password', 'database');
    }

    public function savePost()
    {
        $query = "INSERT INTO posts (text, created_at) VALUES (" . $this->text . ", " . $this->type . ", " . $this->created_at . ")";
        $result = $this->db->query($query);

        if ($result) {
            $this->log("Post has been saved to the database.");
            return true;
        } else {
            $this->log("Failed to save post to the database.");
            http_response_code(500);
            exit;
        }
    }

    private function log($message) {
        file_put_contents('log.txt', $message . PHP_EOL, FILE_APPEND);
    }

    public  function getCreatedAt(): string
    {
        return $this->created_at;
    }

    // ...
}

/**
 * @method User getUser
 * - Нарушается рекомендация тонких контроллеров
 * - Содержится бизнес логика в контрллере (проверка на роль, отправка в редис)
 * - Использование exit;
 * - Magic Number - 10, это антипаттерн, число должно находиться в осмысленной константе
 * - Устанавливается неверный http код ответа, верный будет 201, коды ответа устанавливаются не везде
 * - Сильная связанность за счет использования RedisService, лучше подготовить для него интерфейс и получать через зависимость
 */
class CreatePostController
{
    public function __invoke()
    {
        $user = $this->getUser();
        if ($user->role !== 10) {
            echo json_encode(['message' => 'no access']);
            exit;
        }

        $post = new Post();
        $post->created_at = time();
        $post->text = $_POST['text'];
        $post->type = $_POST['type'];

        if ($post->savePost()) {
            $redisService = new RedisService();
            $redisService->addToEmailQueue($post);

            http_response_code(200);
            exit;
        }
    }
}

/**
 * - Зависимость на RSMQClient, Redis. Лучше вынести это во внешнюю зависимость и использовать интерфейс
 * - Адрес редиса в коде
 * - В теле addToEmailQueue несколько if, в будущем это приведет к их росту.
 *   У этой проблемы много решений, кандидат может предложить собственное
 */
class RedisService
{
    public $redisQueue;

    public function __construct()
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1');

        $this->redisQueue = new RSMQClient($redis);
    }

    public function addToEmailQueue(Post $post)
    {
        if ($post->type === 'blog_post') {
            $this->redisQueue->sendMessage('blog_email_queue', json_encode([
                'text' => $post->text,
                'type' => $post->type,
                'created_at' => $post->created_at
            ]));
        } elseif ($post->type === 'personal_post') {
            $this->redisQueue->sendMessage('personal_email_queue', json_encode([
                'text' => $post->text,
                'created_at' => $post->created_at
            ]));
        }
    }
}
