<?php

namespace App\Acme;

class Account {
  function __construct($db) {
    $this->db = $db;
    $this->sExists = $db->prepare("SELECT id FROM account WHERE id=:id");
    $this->sActive = $db->prepare("SELECT active FROM account WHERE id=:id");
    $this->sOpenAccount = $db->prepare('INSERT INTO account (name, hkid, balance) VALUES (:name, :hkid, :balance)');
    $this->sToggleAccountStatus = $db->prepare("UPDATE account SET active=:active WHERE id=:id");
    $this->sGetBalance = $db->prepare("SELECT balance FROM account WHERE id=:id");
    $this->sSetBalance = $db->prepare("UPDATE account SET balance=:balance WHERE id=:id");
    $this->sGetAccountOwner = $db->prepare("SELECT hkid FROM account WHERE id=:id");
  }

  public function openAccount($name, $hkid) {
    if (!$name) throw new \Exception('"name" is required');
    if (!$hkid) throw new \Exception('"hkid" is required');

    $balance = 0;
    $this->sOpenAccount->bindParam(':name', $name, \PDO::PARAM_STR); // this sanitizes the input as well
    $this->sOpenAccount->bindParam(':hkid', $hkid, \PDO::PARAM_STR);
    $this->sOpenAccount->bindParam(':balance', $balance, \PDO::PARAM_INT);
    $this->sOpenAccount->execute();

    return "account created for ".$name." with balance ".$balance;
  }

  public function closeAccount($id) {
    if (!$id) throw new \Exception('"id" is required');
    if (!$id) throw new \Exception('"id" is required');
    if (!$this->accountExists($id)) throw new \Exception('account doesn\'t exist');
    if ($this->isAccountClosed($id)) throw new \Exception('account is already closed');

    $active = 0;
    $this->sToggleAccountStatus->bindParam(':active', $active, \PDO::PARAM_INT);
    $this->sToggleAccountStatus->bindParam(':id', $id, \PDO::PARAM_INT);
    $this->sToggleAccountStatus->execute();

    return "account closed for ".$id;
  }

  public function getBalance($id) {
    if (!$id) throw new \Exception('"id" is required');
    if (!$this->accountExists($id)) throw new \Exception('account doesn\'t exist');

    $active = 0;
    $this->sGetBalance->bindParam(':id', $id, \PDO::PARAM_INT);
    $this->sGetBalance->execute();
    $result = $this->sGetBalance->fetch(\PDO::FETCH_ASSOC);

    return $result['balance'];
  }

  public function withdraw($id, $amount) {
    if (!$id) throw new \Exception('"id" is required');
    if (!$amount) throw new \Exception('"amount" is required');
    if (!$this->accountExists($id)) throw new \Exception('account doesn\'t exist');
    if ($this->isAccountClosed($id)) throw new \Exception('account is already closed');

    $balance = $this->getBalance($id);
    if ($balance<$amount) throw new \Exception('insufficient funds');

    $newBalance = $balance - $amount;
    $this->sSetBalance->bindParam(':balance', $newBalance, \PDO::PARAM_INT);
    $this->sSetBalance->bindParam(':id', $id, \PDO::PARAM_INT);
    $this->sSetBalance->execute();

    return $amount." withdrawn from account ".$id;
  }

  public function deposit($id, $amount) {
    if (!$id) throw new \Exception('"id" is required');
    if (!$amount) throw new \Exception('"amount" is required');
    if (!$this->accountExists($id)) throw new \Exception('account doesn\'t exist');
    if ($this->isAccountClosed($id)) throw new \Exception('account is already closed');

    $balance = $this->getBalance($id);
    $newBalance = $balance + $amount;
    $this->sSetBalance->bindParam(':balance', $newBalance, \PDO::PARAM_INT);
    $this->sSetBalance->bindParam(':id', $id, \PDO::PARAM_INT);
    $this->sSetBalance->execute();

    return $amount." deposited to account ".$id;
  }

  public function transfer($from, $to, $amount) {
    if (!$from) throw new \Exception('"from" is required');
    if (!$to) throw new \Exception('"to" is required');
    if (!$amount) throw new \Exception('"amount" is required');
    if (!$this->accountExists($from)) throw new \Exception('source account doesn\'t exist');
    if (!$this->accountExists($to)) throw new \Exception('target account doesn\'t exist');
    if ($this->isAccountClosed($from)) throw new \Exception('source account is already closed');
    if ($this->isAccountClosed($to)) throw new \Exception('target account is already closed');

    // figure out owners
    $fromOwner = $this->getAccountOwner($from);
    $toOwner = $this->getAccountOwner($to);

    // got approval?
    if ($fromOwner!=$toOwner) {
      $approval = \json_decode(\file_get_contents("http://handy.travel/test/success.json"));
      if ($approval->status!='success') throw new \Exception('approval required for the transaction');
    }

    // figure out fee
    $fee = $fromOwner==$toOwner ? 0 : 100;

    // enough funds?
    $fromBalance = $this->getBalance($from);
    if ($fromBalance<$amount+$fee) throw new \Exception('insufficient funds in source account');

    // do the thing
    $toBalance = $this->getBalance($to);
    $newFromBalance = $fromBalance - $amount - $fee;
    $newToBalance = $toBalance + $amount;
    $this->db->beginTransaction();
    $this->sSetBalance->bindParam(':balance', $newFromBalance, \PDO::PARAM_INT);
    $this->sSetBalance->bindParam(':id', $from, \PDO::PARAM_INT);
    $this->sSetBalance->execute();
    $this->sSetBalance->bindParam(':balance', $newToBalance, \PDO::PARAM_INT);
    $this->sSetBalance->bindParam(':id', $to, \PDO::PARAM_INT);
    $this->sSetBalance->execute();
    $this->db->commit();

    return "transferred ".$amount." from account ".$from." to account ".$to;
  }

  private function accountExists($id) {
    if (!$id) throw new \Exception('"id" is required');

    $this->sExists->bindParam(':id', $id, \PDO::PARAM_INT);
    $this->sExists->execute();

    return $this->sExists->rowCount() > 0;
  }

  private function isAccountClosed($id) {
    if (!$id) throw new \Exception('"id" is required');

    $this->sActive->bindParam(':id', $id, \PDO::PARAM_INT);
    $this->sActive->execute();
    $result = $this->sActive->fetch(\PDO::FETCH_ASSOC);

    return !$result['active'];
  }

  private function getAccountOwner($id) {
    if (!$id) throw new \Exception('"id" is required');

    $this->sGetAccountOwner->bindParam(':id', $id, \PDO::PARAM_INT);
    $this->sGetAccountOwner->execute();
    $result = $this->sGetAccountOwner->fetch(\PDO::FETCH_ASSOC);

    return $result['hkid'];
  }
}
