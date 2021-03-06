<?php

class User {
    public $id;
    public $email;
    public $name;
    public $avatar;
    public $points;
    public $bailouts;
    public $badges;
    public $status;
    public $created_at;
    public $bets;

    private $token;
    private $pw_hash;

    /**
     * Check login credentials and return the user ID
     * @param string $email
     * @param string $password
     * @return int|bool
     */
    static function Login($email, $password) {
        $db = new DB();

        $data = [
            "email" => $email,
            "pw_hash" => sha1($password)
        ];

        if ($id = $db->fetch("SELECT id FROM User WHERE email = :email AND pw_hash = :pw_hash", $data)) {
            return $id;
        }
        else
            return false;
    }

    /**
     * Get a user instance from an ID
     * @param int $id
     * @return User|bool
     */
    static function Get($id) {
        $db = new DB();

        $data = ["id" => $id];

        if ($user = $db->fetch("SELECT * FROM User WHERE id = :id", $data, 'User')[0]) {
            $user->token = Token::Get($id);
            $user->getAvatars();
            $user->getBadges();
            $user->bets = Bet::GetByUser($user->id);

            return $user;
        } else
            return false;
    }

    /**
     * Get all users
     * @return User[]|bool
     */
    static function GetAll() {
        $db = new DB();

        if ($users = $db->fetch("SELECT * FROM User ORDER BY points DESC", null, 'User')) {
            foreach ($users as &$user) {
                $user->getAvatars();
                $user->getBadges();
                $user->bets = Bet::GetByUser($user->id);
            }
            return $users;
        } else
            return false;
    }

    /**
     * Get a user instance from a token
     * @param int $token
     * @return User|bool
     */
    static function GetByToken($token) {
        $db = new DB();

        $data = ["token" => $token];

        if ($user_id = $db->fetch("SELECT user_id FROM Token WHERE token = :token", $data)) {
            $user = User::Get($user_id);
            return $user;
        } else
            return false;
    }

    /**
     * Create a new user and returns its ID
     * @return int|bool
     */
    function Create() {
        $db = new DB();

        // Create avatar
        $avatar = uniqid();
        $genders = ['male', 'female'];
        $gender = $genders[array_rand($genders)];
        $image = file_get_contents("http://eightbitavatar.herokuapp.com/?id=$this->email&s=$gender&size=150");
        file_put_contents("../static/avatars/${avatar}_150.jpg", $image);
        $image = file_get_contents("http://eightbitavatar.herokuapp.com/?id=$this->email&s=$gender&size=32");
        file_put_contents("../static/avatars/${avatar}_32.jpg", $image);

        $data = [
            "email" => $this->email,
            "pw_hash" => $this->pw_hash,
            "name" => $this->name,
            "avatar" => $avatar
        ];

        if ($user_id = $db->modify("INSERT INTO User (email, pw_hash, name, avatar)
                                    VALUES (:email, :pw_hash, :name, :avatar)", $data)) {
            return $user_id;
        } else
            return false;
    }

    /**
     * Get the user instance properties as an array
     * @return array
     */
    function toArray() {
        return get_object_vars($this);
    }

    /**
     * Get the user instance as a JSON encoded string
     * @return string
     */
    function toJson() {
        return json_encode($this->toArray($this));
    }

    /**
     * Generates a random password. It returns its unencrypted value only once
     * @param int $length
     * @return string
     */
    function createRandomPassword($length) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $password = substr(str_shuffle($chars), 0, $length);
        $this->pw_hash = sha1($password);

        return $password;
    }

    /**
     * Set the user instance's status
     * @param string $status
     */
    function setStatus($status) {
        $db = new DB();

        $data = [
            "id" => $this->id,
            "status" => $status
        ];

        $db->modify("UPDATE User SET status = :status WHERE id = :id", $data);
    }

    /**
     * Returns the current user instance's access token
     * @return string
     */
    function getToken() {
        return $this->token;
    }

    /**
     * Converts the user instance's avatar property to its big and small counterparts
     */
    function getAvatars() {
        $avatar = $this->avatar;
        $this->avatar = [
            'big' => STATIC_URL . "/avatars/${avatar}_150.jpg",
            'small' => STATIC_URL . "/avatars/${avatar}_32.jpg"
        ];
    }

    /**
     * Populates the user instance's badges property with his current badge status
     */
    function getBadges() {
        $db = new DB();

        $data = ["user_id" => $this->id];

        $badges = $db->fetch("SELECT * FROM Badge");
        $user_badges = $db->fetch("SELECT id, name, points FROM UserBadge, Badge WHERE Badge.id = UserBadge.badge_id AND user_id = :user_id", $data);

        foreach ($badges as &$badge) {
            $badge['image'] = STATIC_URL . '/badges/' . $badge['id'] . '.png';

            if ($user_badges) {
                if (isset($user_badges[0])) {
                    foreach ($user_badges as &$user_badge)
                        if ($user_badge['id'] == $badge['id'])
                            $badge['unlocked'] = 1;
                } else {
                    if ($user_badges['id'] == $badge['id'])
                        $badge['unlocked'] = 1;
                }
            }
        }

        $this->badges = $badges;
    }

    /**
     * Awards a badge with the specified id to the user. Ignores if already unlocked
     * @param int $badge_id
     */
    function awardBadge($badge_id) {
        $db = new DB();

        $data = [
            "user_id" => $this->id,
            "badge_id" => $badge_id
        ];

        $db->modify("INSERT IGNORE INTO UserBadge (user_id, badge_id) VALUES (:user_id, :badge_id)", $data);

        // Award the badge points to the user
        foreach ($this->badges as $badge)
            if ($badge['id'] == $badge_id && !isset($badge['unlocked']))
                $this->givePoints($badge['points']);
    }

    /**
     * Give a set amount of points to the user, and if negative, takes away
     * @param int $amount
     */
    function givePoints($amount) {
        $db = new DB();

        $this->points += $amount;

        $data = [
            "user_id" => $this->id,
            "points" => $this->points
        ];

        $db->modify("UPDATE User SET points = :points WHERE id = :user_id", $data);
    }

    /**
     * Bails out the user giving him points and increasing his bailout count
     */
    function bailout() {
        $db = new DB();

        $data = ["user_id" => $this->id];

        $db->modify("UPDATE User SET points = 1000, bailouts = bailouts + 1 WHERE id = :user_id", $data);
    }

    /**
     * Reset the password of the user with the specified email
     * @param string $email
     * @return string|bool
     */
    static function ResetPassword($email) {
        $db = new DB();

        $data = ["email" => $email];

        if ($user = $db->fetch("SELECT * FROM User WHERE email = :email", $data, 'User')[0]) {
            $password = $user->createRandomPassword(6);

            $data = [
                "user_id" => $user->id,
                "pw_hash" => $user->pw_hash
            ];
            $db->modify("UPDATE User SET pw_hash = :pw_hash WHERE id = :user_id", $data);
            $user->setStatus('pending');
            return $password;
        } else
            return false;
    }
}