<?php

namespace App\Entity\Comment;

use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;
use App\Entity\User\User;

/**
 * Comment
 *
 * @ORM\Entity(repositoryClass="App\Repository\CommentRepository")
 * @ORM\Table(name="salt_comment")
 *
 * @Serializer\VirtualProperty(
 *     "fullname",
 *     exp="object.getFullname()",
 *     options={@Serializer\SerializedName("fullname")}
 *  )
 */
class Comment
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var Comment
     *
     * @ORM\ManyToOne(targetEntity="Comment")
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     *
     * @Serializer\Accessor(getter="getParentId")
     * @Serializer\ReadOnly
     * @Serializer\Type("int")
     */
    private $parent;

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User\User")
     * @ORM\JoinColumn(nullable=false)
     *
     * @Serializer\Exclude()
     */
    private $user;

    /**
     * @var LsDoc
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Framework\LsDoc")
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     */
    private $document;

    /**
     * @var LsItem
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Framework\LsItem")
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     */
    private $item;

    /**
     * @var CommentUpvote[]|Collection
     *
     * @ORM\OneToMany(targetEntity="CommentUpvote", mappedBy="comment")
     *
     * @Serializer\Accessor(getter="getUpvoteCount")
     * @Serializer\ReadOnly
     * @Serializer\Type("int")
     * @Serializer\SerializedName("upvote_count")
     */
    private $upvotes;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", precision=6)
     * @Serializer\SerializedName("created")
     * @Gedmo\Timestampable(on="create")
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", precision=6)
     * @Serializer\SerializedName("modified")
     * @Gedmo\Timestampable(on="update")
     */
    private $updatedAt;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $fileUrl;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $fileMimeType;

    /**
     * @var bool
     */
    private $createdByCurrentUser;

    /**
     * @var bool
     */
    private $userHasUpvoted;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->upvotes = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set parent
     *
     * @param Comment|null $parent
     *
     * @return Comment
     */
    public function setParent(Comment $parent = null): Comment
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return Comment|null
     */
    public function getParent(): ?Comment
    {
        return $this->parent;
    }

    /**
     * Get the id of a parent
     *
     * @return int|null
     */
    public function getParentId(): ?int
    {
        if (null !== $this->parent) {
            return $this->parent->getId();
        }

        return null;
    }

    /**
     * Set content
     *
     * @param string $content
     *
     * @return Comment
     */
    public function setContent($content): Comment
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set user
     *
     * @param User $user
     *
     * @return Comment
     */
    public function setUser(User $user): Comment
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Set item
     *
     * @param LsItem $item
     *
     * @return Comment
     */
    public function setItem(LsItem $item): Comment
    {
        $this->item = $item;

        return $this;
    }

    /**
     * Get item
     *
     * @return LsItem
     */
    public function getItem(): LsItem
    {
        return $this->item;
    }

    /**
     * Set document
     *
     * @param LsDoc $document
     *
     * @return Comment
     */
    public function setDocument(LsDoc $document): Comment
    {
        $this->document = $document;

        return $this;
    }

    /**
     * Get document
     *
     * @return LsDoc
     */
    public function getDocument(): LsDoc
    {
        return $this->document;
    }

    /**
     * Get fullname
     *
     * @return string
     */
    public function getFullname(): string
    {
        return preg_replace('/@.*/', '', $this->getUser()->getUsername());
    }

    /**
     * Set createdByCurrentUser
     *
     * @param bool $createdByCurrentUser
     *
     * @return Comment
     */
    public function setCreatedByCurrentUser(bool $createdByCurrentUser): Comment
    {
        $this->createdByCurrentUser = $createdByCurrentUser;

        return $this;
    }

    public function updateStatusForUser(User $user): Comment
    {
        if ($this->getUser()->getId() === $user->getId()) {
            $this->setCreatedByCurrentUser(true);
        }

        foreach ($this->getUpvotes() as $upvote) {
            if ($upvote->getUser()->getId() === $user->getId()) {
                $this->setUserHasUpvoted(true);
                break;
            }
        }

        return $this;
    }

    /**
     * Get createdByCurrentUser
     *
     * @return bool
     */
    public function isCreatedByCurrentUser(): bool
    {
        return $this->createdByCurrentUser;
    }

    /**
     * Add upvote
     *
     * @param CommentUpvote $upvote
     *
     * @return Comment
     */
    public function addUpvote(CommentUpvote $upvote): Comment
    {
        $this->upvotes[] = $upvote;

        return $this;
    }

    /**
     * Remove upvote
     *
     * @param CommentUpvote $upvote
     *
     * @return Comment
     */
    public function removeUpvote(CommentUpvote $upvote): Comment
    {
        $this->upvotes->removeElement($upvote);

        return $this;
    }

    /**
     * Get upvotes
     *
     * @return Collection
     */
    public function getUpvotes(): Collection
    {
        return $this->upvotes;
    }

    /**
     * Get upvoteCount
     *
     * @return int
     */
    public function getUpvoteCount(): int
    {
        return $this->upvotes->count();
    }

    /**
     * Set userHasUpvoted
     *
     * @param bool $userHasUpvoted
     *
     * @return Comment
     */
    public function setUserHasUpvoted(bool $userHasUpvoted): Comment
    {
        $this->userHasUpvoted = $userHasUpvoted;

        return $this;
    }

    /**
     * Get userHasUpvoted
     *
     * @return bool
     */
    public function hasUserUpvoted(): bool
    {
        return $this->userHasUpvoted;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Comment
     */
    public function setCreatedAt(\DateTime $createdAt): Comment
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return Comment
     */
    public function setUpdatedAt(\DateTime $updatedAt): Comment
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Set fileUrl
     *
     * @param string $fileUrl
     *
     * @return Comment
     */
    public function setFileUrl($fileUrl): Comment
    {
        $this->fileUrl = $fileUrl;

        return $this;
    }

    /**
     * Get fileUrl
     *
     * @return string
     */
    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    /**
     * Set fileMimeType
     *
     * @param string $fileMimeType
     *
     * @return Comment
     */
    public function setFileMimeType($fileMimeType): Comment
    {
        $this->fileMimeType = $fileMimeType;

        return $this;
    }

    /**
     * Get fileMimeType
     *
     * @return string
     */
    public function getFileMimeType(): string
    {
        return $this->fileMimeType;
    }
}
