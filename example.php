<?php

require_once __DIR__ . '/vendor/autoload.php';

interface IModel {}
abstract class BaseModel implements IModel{}

interface IUserModel extends IModel {}
class UserModel extends BaseModel implements IUserModel {}

interface IAccountModel extends IModel {
    public function getUser(): IUserModel;
}
class AccountModel extends BaseModel implements IAccountModel {
    private IUserModel $user;

    public function __construct(IUserModel $user)
    {
        $this->user = $user;
    }

    public function getUser(): IUserModel
    {
        return $this->user;
    }
}

interface ICharacterAccount extends IAccountModel {}
class CharacterAccountModel extends AccountModel implements ICharacterAccount {
    private IAccountModel $userAccount;

    public function __construct(IAccountModel $userAccount)
    {
        parent::__construct($userAccount->getUser());

        $this->userAccount = $userAccount;
    }
}

\ObjectFactory\Factory::registerInterfaceClass(IUserModel::class, UserModel::class);
\ObjectFactory\Factory::registerInterfaceInstanceProvider(IAccountModel::class, function (): IAccountModel {
    return \ObjectFactory\Factory::getInstance(AccountModel::class);
});
\ObjectFactory\Factory::registerInterfaceClass(ICharacterAccount::class, CharacterAccountModel::class);

$nonShared = \ObjectFactory\Factory::getInstance(AccountModel::class);
$sharedA = \ObjectFactory\Factory::getSharedInstance(AccountModel::class);
$sharedB = \ObjectFactory\Factory::getSharedInstance(AccountModel::class);
\ObjectFactory\Factory::getInstance(CharacterAccountModel::class);

var_dump($nonShared === $sharedA, $sharedA === $sharedB);