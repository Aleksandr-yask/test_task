<?php

abstract class Model
{
    abstract public function getCreatedAt(): string;
}

class User extends Model
{
    public function __construct(
        private string $username,
        private string $email,
        private string $role,
        private ?string $id = null,
        private ?string $createdAt = null,
    ) {
        if ($this->createdAt === null) {
            $this->createdAt = time();
        }
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}

class Post
{
    public function __construct(
        private string $text,
        private string $type,
        private ?string $createdAt = null,
        private ?string $id = null,
    ) {
        if ($this->createdAt = null) {
            $this->createdAt = time();
        }
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public  function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    // ...
}

interface PostRepositoryInterface
{
    public function createPost(Post $post): void;
}

/**
 * Репозитории лучше использовать через интерфейсы, это снизит завязку на конкретную реализацию базы данных
 */
class PostRepository implements PostRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private LoggerInterface $logger
    ) {
    }

    public function createPost(Post $post): void
    {
        $query = "INSERT INTO posts (text, created_at) VALUES (%s, %s)";
        $result = $this->connection->query(
            $query,
            [
                $post->getText(),
                $post->getCreatedAt()
            ]
        );

        if ($result) {
            $this->logger->log("Post has been saved to the database.");
        } else {
            $this->logger->log("Failed to save post to the database.");

            throw new LogicException('Saving error');
        }
    }
}

/**
 * @method User getUser
 */
class CreatePostController
{
    public function __construct(
        readonly private PostCreator $creator,
        readonly private PostService $postService,
    ) {
    }

    public function __invoke(HttpRequest $request)
    {
        $this->postService->savePost(
            $this->creator->createPostByRequest($request)
        );

        return new Response(null, [], Response::HTTP_CREATED);
    }
}

enum UserRoles: int
{
    case CREATOR = 10;
    case READER = 9;
}

/**
 * Пример реализации проверки на симфоневом вотере
 */
class CreatePostVoter
{
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return $token->getUser()->getRole() === UserRoles::CREATOR;
    }
}

/**
 * Реализации могут быть другие, главное что бы вынести логику создания из контроллера
 */
class PostCreator
{
    public function createPostByRequest(HttpRequest $request): Post
    {
        return new Post(
            $request->getPostFields()['text'],
            $request->getPostFields()['type'],
        );
    }
}

class PostService
{
    public function __construct(
        readonly private PostRepositoryInterface $postRepository,
        readonly private MailerInterface $mailer,
    ) {
    }

    public function savePost(Post $post)
    {
        $this->postRepository->createPost($post);
        $this->mailer->send($post);
    }
}

interface MailerInterface
{
    public function send(mixed $object): void;
    public function support(mixed $object): bool;
}

class BlogPostAsyncMailer implements MailerInterface
{
    private const QUEUE_NAME = 'blog_email_queue';

    public function __construct(readonly private QueueInterface $queue)
    {
    }

    /**
     * @param Post $object
     */
    public function send(mixed $object): void
    {
        $this->queue->sendMessage(self::QUEUE_NAME, json_encode([
            'text' => $object->getText(),
            'type' => $object->getType(),
            'created_at' => $object->getCreatedAt(),
        ]));
    }

    public function support(mixed $object): bool
    {
        return $object instanceof Post;
    }
}
