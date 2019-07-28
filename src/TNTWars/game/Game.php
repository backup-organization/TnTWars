<?php


namespace TNTWars\game;


use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\Task;
use pocketmine\utils\Color;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use TNTWars\TNTWars;

class Game
{

    const STATE_LOBBY = 0;
    const STATE_RUNNING = 1;


    /** @var TNTWars $plugin */
    private $plugin;

    /** @var string $gameName */
    private $gameName;

    /** @var int $minPlayers */
    private $minPlayers;

    /** @var int $maxPlayers */
    private $maxPlayers;

    /** @var string $worldName */
    private $worldName;

    /** @var array $teamInfo */
    private $teamInfo = array();

    /** @var int $state */
    private $state = self::STATE_LOBBY;

    /** @var array $players */
    public $players = array();

    /** @var array $spectators */
    public $spectators = array();

    /** @var bool $starting */
    private $starting = false;

    /** @var Vector3 $lobby */
    private $lobby;

    /** @var int $startTime */
    private $startTime = 30;

    /** @var int $voidY */
    private $voidY ;

    /** @var Team[] $teams */
    public $teams = array();

    /** @var array $gold*/
    private $gold = [];



    /**
     * Game constructor.
     * @param TNTWars $plugin
     * @param string $arenaName
     * @param int $minPlayers
     * @param int $maxPlayers
     * @param string $worldName
     */
    public function __construct(TNTWars $plugin, string $arenaName, int $minPlayers, int $maxPlayers, string $worldName, array $teamInfo)
    {
        $this->plugin = $plugin;
        $this->gameName = $arenaName;
        $this->minPlayers = $minPlayers;
        $this->maxPlayers = $maxPlayers;
        $this->worldName = $worldName;
        $this->teamInfo = $teamInfo;

        foreach($this->teamInfo as $teamName => $teamData){
             $this->teams[$teamName] = new Team($teamName, array_search($teamName, TNTWars::TEAMS), $teamData['shipDirection']['coord'], $teamData['shipDirection']['countType']);
        }

        $this->reload();

        $this->plugin->getScheduler()->scheduleRepeatingTask(new class($this) extends Task{

            private $plugin;

            /**
             *  constructor.
             * @param Game $plugin
             */
            public function __construct(Game $plugin)
            {
                $this->plugin = $plugin;
            }

            public function onRun(int $currentTick)
            {
                $this->plugin->tick();
            }
        }, 20);



    }

    /**
     * @param int $limit
     */
    public function setVoidLimit(int $limit) : void{
        $this->voidY = $limit;
    }

    /**
     * @param Vector3 $lobby
     */
    public function setLobby(Vector3 $lobby) : void{
        $this->lobby = new Position($lobby->x, $lobby->y, $lobby->z, $this->plugin->getServer()->getLevelByName($this->worldName));
    }

    /**
     * @return int
     */
    public function getVoidLimit() : int{
        return $this->voidY;
    }

    /**
     * @return int
     */
    public function getState() : int{
        return $this->state;
    }

    /**
     * @return string
     */
    public function getName() : string{
        return $this->gameName;
    }

    /**
     * @return int
     */
    public function getMaxPlayers() : int{
        return $this->maxPlayers;
    }

    public function reload() : void{
        $this->plugin->getServer()->loadLevel($this->worldName);
        $world = $this->plugin->getServer()->getLevelByName($this->worldName);
        if(!$world instanceof Level){
            $this->plugin->getLogger()->info(TNTWars::PREFIX . TextFormat::YELLOW . "Failed to load arena " . $this->gameName . " because it's world does not exist!");
            return;
        }
        $world->setAutoSave(false);
    }

    /**
     * @param string $message
     */
    public function broadcastMessage(string $message) : void{
        foreach(array_merge($this->spectators, $this->players) as $player){
            $player->sendMessage(TNTWars::PREFIX . $message);
        }
    }

    public function stop() : void{
        if(count($this->players) == 1){
            $winner = "";
            foreach($this->players as $player){
                $winner = $player->getName();
                $economy = $this->plugin->getServer()->getPluginManager()->getPlugin("EconomyAPI");
                if($economy instanceof Plugin){
                    $economy->addMoney($player, 150);
                }
            }
            $this->plugin->getServer()->broadcastMessage(TextFormat::GREEN . $winner . TextFormat::AQUA . " has won the game on arena " . TextFormat::GREEN . $this->gameName);
        }

        foreach(array_merge($this->players, $this->spectators) as $player){
            $player->setHealth($player->getMaxHealth());
            $player->setFood(20);
            $player->setGamemode(0);
        }

        $this->spectators = [];
        $this->players = [];
        $this->startTime = 60;
        $this->state = self::STATE_LOBBY;
        $this->starting = false;
        $this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName($this->worldName));
        $this->reload();
        $this->setLobby(new Vector3($this->lobby->x, $this->lobby->y, $this->lobby->z));

    }

    public function start() : void{
         $this->broadcastMessage(TextFormat::GREEN . "Game has started!");
         $this->state = self::STATE_RUNNING;

         $splitPlayers = array_chunk($this->players, ceil(count($this->players) / 2));
         $a = 0;

         $level = $this->plugin->getServer()->getLevelByName($this->worldName);
         foreach($this->teams as $team){
             $spawn = $this->teamInfo[$team->getName()]['spawn'];
             $spawn = explode(":", $spawn);
             foreach($splitPlayers[$a] as $player){
                 $team->addPlayer($player);
                 $player->setNameTag($team->getColor() . $player->getName());
                 $player->teleport(new Position(intval($spawn[0]), intval($spawn[1]), intval($spawn[2]), $level));

                 $helmet = Item::get(Item::LEATHER_HELMET);
                 $chestplate = Item::get(Item::LEATHER_CHESTPLATE);
                 $leggings = Item::get(Item::LEATHER_LEGGINGS);
                 $boots = Item::get(Item::LEATHER_BOOTS);

                 foreach([$helmet, $chestplate, $leggings, $boots] as $armor){
                     $armor->setCustomColor(Color::get)
                 }
             }
         }
    }

    /**
     * @param Player $player
     */
    public function join(Player $player) : void{
         if($this->state !== self::STATE_LOBBY){
             $player->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "You can't join at the moment!");
             return;
         }

         if(count($this->players) >= $this->maxPlayers){
             $player->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Arena is full!");
             return;
         }

         $player->teleport($this->lobby);
         $this->players[$player->getRawUniqueId()] = $player;
         $this->gold[$player->getRawUniqueId()] = 0;

         $this->broadcastMessage(TextFormat::YELLOW . $player->getName() . " " . TextFormat::AQUA . "has joined the game " . TextFormat::GOLD . "[" . count($this->players) . "/" . $this->maxPlayers . "]");
         $this->checkLobby();

    }

    /**
     * @param Player $player
     */
    public function quit(Player $player) : void{
         if(isset($this->players[$player->getRawUniqueId()])){
             unset($this->players[$player->getRawUniqueId()]);
         }
         if(isset($this->spectators[$player->getRawUniqueId()])){
             unset($this->spectators[$player->getRawUniqueId()]);
         }
    }

    private function checkLobby() : void{
        if(!$this->starting && count($this->players) >= $this->minPlayers){
            $this->starting = true;
            $this->broadcastMessage(TextFormat::GREEN . "Countdown started");
        }elseif($this->starting && count($this->players) < $this->minPlayers){
            $this->starting = false;
            $this->broadcastMessage(TextFormat::YELLOW . "Countdown stopped");
        }
    }

    /**
     * @param Player $player
     */
    public function killPlayer(Player $player) : void{
        if((count($this->players) - 1) > 1){
            $this->spectators[$player->getRawUniqueId()] = $player;
            unset($this->players[$player->getRawUniqueId()]);
            $player->setGamemode(Player::SPECTATOR);
            $player->addTitle(TextFormat::RED . "You died!", TextFormat::YELLOW . "You are now spectating");
            $player->teleport($this->lobby);
        }else{
            $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
        }
    }


    public function tick() : void{
         switch($this->state){
             case self::STATE_LOBBY;
             if($this->starting){
                 $this->startTime--;

                 foreach($this->players as $player){
                     $player->sendTip(TextFormat::YELLOW . "Starting in " . TextFormat::AQUA . gmdate("i:s", $this->startTime));
                 }

                 switch($this->startTime){
                     case 30;
                     $this->broadcastMessage(TextFormat::YELLOW . "Starting in " . TextFormat::RED . "30");
                     break;
                     case 15;
                     $this->broadcastMessage(TextFormat::YELLOW . "Starting in " . TextFormat::GOLD . "15");
                     break;
                     case 5;
                     case 4;
                     case 3;
                     case 2;
                     case 1;
                     foreach($this->players as $player){
                         $player->addTitle(TextFormat::RED . $this->startTime);
                     }
                     break;
                 }

                 if($this->startTime == 0){
                     $this->start();
                 }
             }else{
                 foreach($this->players as $player){
                     $player->sendTip(TextFormat::YELLOW . "Waiting for players (" . TextFormat::AQUA . ($this->minPlayers - count($this->players)) . TextFormat::YELLOW .  ")");
                 }
             }
             break;
             case self::STATE_RUNNING;
             foreach($this->players as $player){
                 $player->sendTip(TextFormat::YELLOW . "Gold: " . TextFormat::AQUA . $this->gold[$player->getRawUniqueId()] . " " . TextFormat::GRAY . "| " . TextFormat::YELLOW . "Players remaining: " . TextFormat::AQUA . count($this->players));
                 $this->gold[$player->getRawUniqueId()]+= rand(1,2);
             }

             if(count($this->players) <= 1){
                // $this->stop();
             }
             break;
         }
    }







}