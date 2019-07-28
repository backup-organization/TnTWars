<?php


namespace TNTWars\ships;


use pocketmine\Player;

interface ShipInterface
{

    public function isCollided() : bool;

    public function place(Player $player) : void;

}