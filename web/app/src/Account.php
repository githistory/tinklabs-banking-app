<?php

namespace App\Acme;

class Account {
  function __construct($db) {
    $this->db = $db;
    $this->sExists = $db->prepare("SELECT id FROM account WHERE id=:id");
    $this->sActive = $db->prepare("SELECT active FROM account WHERE id=:id");
    $this->sOpenAccount = $db->prepare('INSERT INTO account (name, balance) VALUES (:name, :balance)');
    $this->sToggleAccountStatus = $db->prepare("UPDATE account SET active=:active WHERE id=:id");
    $this->sGetBalance = $db->prepare("SELECT balance FROM account WHERE id=:id");
    $this->sSetBalance = $db->prepare("UPDATE account SET balance=:balance WHERE id=:id");
  }

  public function openAccount($name) {
    if (!$name) throw new \Exception('"name" is required');

    $balance = 0;
    $this->sOpenAccount->bindParam(':name', $name, \PDO::PARAM_STR); // this sanitizes the input as well
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

    if (!$result['balance']) throw new \Exception("balance not found for id ".$id);
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

    $fromBalance = $this->getBalance($from);
    if ($fromBalance<$amount) throw new \Exception('insufficient funds in source account');

    $toBalance = $this->getBalance($to);
    $newFromBalance = $fromBalance - $amount;
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
}
