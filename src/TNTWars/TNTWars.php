<?php


namespace TNTWars;


use pocketmine\block\utils\SignText;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use TNTWars\command\DefaultCommand;
use TNTWars\game\Game;
use TNTWars\game\GameListener;

class TNTWars extends PluginBase
{

    const PREFIX = TextFormat::BOLD . TextFormat::DARK_GREEN . "TnTWars " . TextFormat::RESET;

    /** @var Game[] $games */
    public $games = array();

    /** @var array $signs */
    public $signs = array();

    const TEAMS = [
        "§a" => "green",
        "§c" => "red",
        "§e" => "yellow",
        "§d" => "purple",
        "§b" => "aqua",
        "§9" => "blue"
    ];


    public function onEnable() : void
    {
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . "arenas");

        //load arenas

        $this->signTick();
        $this->getServer()->getPluginManager()->registerEvents(new GameListener($this), $this);

        foreach(glob($this->getDataFolder() . "arenas/*.json") as $location){
            $fileContents = file_get_contents($location);
            $jsonData = json_decode($fileContents, true);

            if(!$this->validateGame($jsonData)){
                $arenaName = basename($fileContents, ".json");
                $this->getLogger()->info(self::PREFIX . TextFormat::YELLOW . "Failed to load arena " . $arenaName . " because it's data are corrupted!");
                continue;
            }

            if(count($jsonData['signs']) > 0){
                $this->signs[$jsonData['name']] = $jsonData['signs'];
            }



            $this->games[$jsonData['name']] = $game = new Game($this, $jsonData['name'], intval($jsonData['minPlayers']), intval($jsonData['maxPlayers']), $jsonData['world'], $jsonData['teamInfo']);

            $split = explode(":", $jsonData['lobby']);
            $game->setLobby(new Vector3(intval($split[0]), intval($split[1]), intval($split[2])));

            $game->setVoidLimit(intval($jsonData['void_y']));

        }

        //load commands
        $this->getServer()->getCommandMap()->register("tntwars", new DefaultCommand($this));

    }

    /**
     * @param array $arenaData
     * @return bool
     */
    public function validateGame(array $arenaData) : bool{
        $requiredParams = [
            'name',
            'minPlayers',
            'maxPlayers',
            'lobby',
            'world',
            'teamInfo'
        ];

        $error = 0;
        foreach($requiredParams as $param){
            if(!in_array($param, array_keys($arenaData))){
                $error ++;
            }
        }

        if(count($arenaData['teamInfo']) == 0){
            $error++;
        }

        return !$error > 0;
    }

    private function signTick() : void{
        $this->getScheduler()->scheduleRepeatingTask(
            new class($this) extends Task{

                /** @var TNTWars $plugin */
                private $plugin;

                public function __construct(TNTWars $plugin)
                {
                    $this->plugin = $plugin;
                }

                /**
                 * @param int $currentTick
                 */
                public function onRun(int $currentTick) : void
                {
                    foreach ($this->plugin->signs as $arena => $positions) {
                        foreach ($positions as $position) {
                            $pos = explode(":", $position);
                            $vector = new Vector3(intval($pos[0]), intval($pos[1]), intval($pos[2]));

                            $level = $this->plugin->getServer()->getLevelByName($pos[3]);

                            if (!$level instanceof Level) {
                                continue;
                            }

                            if (!in_array($arena, array_keys($this->plugin->games))) {
                                continue;
                            }

                            $game = $this->plugin->games[$arena];
                            $tile = $level->getTile($vector);
                            if (!$tile instanceof Sign) {
                                continue;
                            }

                            $tile->setText(TextFormat::BOLD . TextFormat::DARK_GREEN . "TnTWars",
                                TextFormat::AQUA . "[" . count($game->players) . "/" . $game->getMaxPlayers() . "]",
                                TextFormat::BOLD . TextFormat::GOLD . $game->getName(),
                                $this->getStatus($game->getState()));


                        }
                    }
                }

                /**
                 * @param int $state
                 * @return string
                 */
                public function getStatus(int $state) : string{
                    switch($state){
                        case 0;
                        return TextFormat::YELLOW . "Touch Me";
                        case 1;
                        return TextFormat::RED . "InGame";
                    }
                    return "";
                }


            }, 20
        );
    }



}