<?php


namespace TNTWars\command;


use pocketmine\block\Block;
use pocketmine\block\TNT;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use TNTWars\game\Game;
use pocketmine\math\Vector3;
use TNTWars\TNTWars;

class DefaultCommand extends PluginCommand
{

    /** @var array $directionSetup */
    public $directionSetup = [];


    /**
     * DefaultCommand constructor.
     * @param Plugin $owner
     */
    public function __construct(Plugin $owner)
    {
        parent::__construct("tntwars", $owner);

        //initialize the command
        parent::setDescription("TnTRun command");
        parent::setPermission("tntrun.command");
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return bool|mixed|void
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if(empty($args[0])){
            $this->sendUsage($sender);
            return;
        }

        switch(strtolower($args[0])){
            case "list";
            $sender->sendMessage(TextFormat::BOLD . TextFormat::DARK_RED . "Arena List");
            foreach($this->getPlugin()->games as $game){
                $sender->sendMessage(TextFormat::GREEN . $game->getName());
                //todo: add other info
            }
            break;
            case "create";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            if(count($args) < 3){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "/tntwars create " . TextFormat::YELLOW . "[name] [minPlayers] [maxPlayers]");
                return;
            }

            $gameName = $args[1];
            if(in_array($gameName, array_keys($this->getPlugin()->games))){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Game called " . $gameName . " already exists!");
                return;
            }

            if(!is_int(intval($args[2]))){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "minPlayers must be a number!");
            }

            if(!is_int(intval($args[3]))){
                    $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "maxPlayers must be a number!");
            }

            $minPlayers = intval($args[2]);
            $maxPlayers = intval($args[3]);

            $world = $sender->level;


            $void_y = Level::Y_MAX;
            foreach ($world->getChunks() as $chunk) {
                for ($x = 0; $x < 16; ++$x) {
                    for ($z = 0; $z < 16; ++$z) {
                        for ($y = 0; $y < $void_y; ++$y) {
                            $block = $chunk->getBlockId($x, $y, $z);
                            if ($block !== Block::AIR) {
                                $void_y = $y;
                                break;
                            }
                        }
                    }
                }
            }
                --$void_y;

            $dataStructure = [
                'name' => $gameName,
                'minPlayers' => $minPlayers,
                'maxPlayers' => $maxPlayers,
                'world' => $world->getFolderName(),
                'void_y' => $void_y,
                'signs' => [],
                'teamInfo' => []
            ];

            new Config($this->getPlugin()->getDataFolder() . "arenas/" . $gameName . ".json", Config::JSON, $dataStructure);
            $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "Game created! Type /tntwars to see the other setup commands!");

            break;
            case "delete";
            if(count($args) < 2){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "/tntwars delete " . TextFormat::YELLOW . "[name]");
                return;
            }

            $gameName = $args[1];
            if(!in_array($gameName, array_keys($this->getPlugin()->games))) {
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Game called " . $gameName . " doesn't exist!");
                return;
            }

            //close the arena if it's running
            $gameObject = $this->getPlugin()->games[$gameName];
            if(!$gameObject instanceof Game){
                return; //wtf ??
            }

            $gameObject->stop();

            unlink($this->getPlugin()->getDataFolder() . "arenas/" . $gameName . ".json");
            $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "Arena has been deleted!");

            break;
            case "setlobby";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            if(count($args) < 2){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "/tntwars setlobby " . TextFormat::YELLOW . "[game]");
                return;
            }

            $gameName = $args[1];

            $location = $this->getPlugin()->getDataFolder() . "arenas/" . $gameName . ".json";
            if(!is_file($location)){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Arena doesn't exist");
                return;
            }

            $fileContent = file_get_contents($location);
            $jsonData = json_decode($fileContent, true);
            $positionData = [
                'lobby' => $sender->getX() . ":" . $sender->getY() . ":" . $sender->getZ()
            ];

            file_put_contents($location, json_encode(array_merge($jsonData, $positionData)));

            $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "Spawn has been set!");

            //todo: MOVE THIS INTO SEPARATE COMMAND
          /*  $sender->sendMessage(TextFormat::GREEN . "Reloading arena...");

            $this->getPlugin()->games[$gameName] = $game = new Game($this->getPlugin(), $gameName, intval($jsonData['minPlayers']), intval($jsonData['maxPlayers']), $jsonData['world']);
            $sender->sendMessage(TextFormat::GREEN . "Arena reloaded!");
            $pos = explode(":", $positionData['lobby']);
            $game->setLobby(new Vector3($pos[0], $pos[1], $pos[2]));
            $game->setVoidLimit($void_y);*/
            break;
            case "addteam";
            if(count($args) < 3){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "/tntwars addteam " . TextFormat::YELLOW . "[game] [teamName]");
                return;
            }

            $gameName = $args[1];

            $location = $this->getPlugin()->getDataFolder() . "arenas/" . $gameName . ".json";
            if(!is_file($location)){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Arena doesn't exist");
                return;
            }

            $fileContent = file_get_contents($location);
            $jsonData = json_decode($fileContent, true);

            if(count($jsonData['teamInfo']) >= 2){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "You can create only 2 teams per game!");
                return;
            }

            if(isset($jsonData['teamInfo'][$args[2]])){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Team already exists!");
                return;
            }

            $jsonData['teamInfo'][$args[2]] = ['shipDirection' => ['coord' => 'X', 'countType' => 'ADD']];

            file_put_contents($location, json_encode($jsonData));
            $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "Team added!");
            break;
            case "setdirection";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            if(count($args) < 3){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "/tntwars setdirection " . TextFormat::YELLOW . "[game] [teamName]");
                return;
            }

            $gameName = $args[1];

            $location = $this->getPlugin()->getDataFolder() . "arenas/" . $gameName . ".json";
            if(!is_file($location)){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Arena doesn't exist");
                return;
            }

            $fileContent = file_get_contents($location);
            $jsonData = json_decode($fileContent, true);

            if($jsonData['world'] !== $sender->level->getFolderName()){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "You need to be in the world to use this!");
                return;
            }

            if(!isset($jsonData['teamInfo'][$args[2]])){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Team doesn't exist!");
                return;
            }

            $lookingAt = $sender->getTargetBlock(3);

            if($lookingAt->getX() !== $sender->getX()){
                $jsonData['teamInfo'][$args[2]]['shipDirection']['coord'] = 'X';
                if($lookingAt->getX() < $sender->getX()){
                    $jsonData['teamInfo'][$args[2]]['shipDirection']['countType'] = 'SUBSTRACT';
                }else{
                    $jsonData['teamInfo'][$args[2]]['shipDirection']['countType'] = 'ADD';
                }
                goto a;
            }

            if($lookingAt->getZ() !== $sender->getZ()){
                $jsonData['teamInfo'][$args[2]]['shipDirection']['coord'] = 'Z';
                if($lookingAt->getZ() < $sender->getZ()){
                    $jsonData['teamInfo'][$args[2]]['shipDirection']['countType'] = 'SUBSTRACT';
                }else{
                    $jsonData['teamInfo'][$args[2]]['shipDirection']['countType'] = 'ADD';
                }
            }

            a:
            file_put_contents($location, json_encode($jsonData));
            $sender->sendMessage(TextFormat::GREEN . "Ship direction has been set!");
            break;

            case "setspawn";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            if(count($args) < 3){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::GREEN . "/tntwars setspawn " . TextFormat::YELLOW . "[game] [teamName]");
                return;
            }

            $gameName = $args[1];

            $location = $this->getPlugin()->getDataFolder() . "arenas/" . $gameName . ".json";
            if(!is_file($location)){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Arena doesn't exist");
                return;
            }

            $fileContent = file_get_contents($location);
            $jsonData = json_decode($fileContent, true);

            if($jsonData['world'] !== $sender->level->getFolderName()){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "You need to be in the world to use this!");
                return;
            }

            if(!isset($jsonData['teamInfo'][$args[2]])){
                $sender->sendMessage(TNTWars::PREFIX . TextFormat::YELLOW . "Team doesn't exist!");
                return;
            }

            $jsonData['teamInfo'][$args[2]]['spawn'] = $sender->getX() . ":" .  $sender->getY() . ":" .  $sender->getZ();
            file_put_contents($location, json_encode($jsonData));
            $sender->sendMessage(TextFormat::GREEN . "Spawn has been set!");
            break;


        }
    }

    /**
     * @param CommandSender $sender
     */
    private function sendUsage(CommandSender $sender) : void{
        $sender->sendMessage(TextFormat::BOLD . TextFormat::DARK_RED . "TnTWars Commands");
        $sender->sendMessage(TextFormat::GREEN . "/tntwars list " . TextFormat::YELLOW . "Display list of loaded games");
        $sender->sendMessage(TextFormat::GREEN . "/tntwars create " . TextFormat::YELLOW . "Create new game");
        $sender->sendMessage(TextFormat::GREEN . "/tntwars delete " . TextFormat::YELLOW . "Delete existing game");
        $sender->sendMessage(TextFormat::GREEN . "/tntwars setlobby " . TextFormat::YELLOW . "Set spawning position of a game");
        $sender->sendMessage(TextFormat::GREEN . "/tntwars setspawn " . TextFormat::YELLOW . "Set spawning position of a team");
        $sender->sendMessage(TextFormat::GREEN . "/tntwars setdirection " . TextFormat::YELLOW . "Set ship direction for specific team");
        $sender->sendMessage(TextFormat::GREEN . "/tntwars addteam " . TextFormat::YELLOW . "Create new team for a game");
    }

}