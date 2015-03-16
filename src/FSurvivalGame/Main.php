<?php
namespace FSurvivalGame;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\OfflinePlayer;
use pocketmine\utils\Config;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\math\Vector3;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;
use pocketmine\block\Block;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign;
use pocketmine\tile\Tile;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use onebone\economyapi\EconomyAPI;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use FChestReset\Main as FChestReset;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerMoveEvent;

class Main extends PluginBase implements Listener
{
	/*Status-------------
	0.人数不足等待加入
	1.人数足够等待倒计时
	2.无敌状态
	3.游戏进行
	4.决赛状态
	*/
	private static $obj = null;
	public static function getInstance()
	{
		return self::$obj;
	}
	public function onEnable()
	{
		if(!self::$obj instanceof Main)
		{
			self::$obj = $this;
		}
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"gameTimber"]),20);
		@mkdir($this->getDataFolder(), 0777, true);
		$this->config=new Config($this->getDataFolder() . "config.yml", Config::YAML, array());
		if($this->config->exists("lastpos"))
		{
			$this->sign=$this->config->get("sign");
			$this->pos1=$this->config->get("pos1");
			$this->pos2=$this->config->get("pos2");
			$this->pos3=$this->config->get("pos3");
			$this->pos4=$this->config->get("pos4");
			$this->pos5=$this->config->get("pos5");
			$this->pos6=$this->config->get("pos6");
			$this->pos7=$this->config->get("pos7");
			$this->pos8=$this->config->get("pos8");
			$this->lastpos=$this->config->get("lastpos");
			$this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);
			$this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
			$this->sign=new Vector3($this->sign["x"],$this->sign["y"],$this->sign["z"]);
			$this->pos1=new Vector3($this->pos1["x"]+0.5,$this->pos1["y"],$this->pos1["z"]+0.5);
			$this->pos2=new Vector3($this->pos2["x"]+0.5,$this->pos2["y"],$this->pos2["z"]+0.5);
			$this->pos3=new Vector3($this->pos3["x"]+0.5,$this->pos3["y"],$this->pos3["z"]+0.5);
			$this->pos4=new Vector3($this->pos4["x"]+0.5,$this->pos4["y"],$this->pos4["z"]+0.5);
			$this->pos5=new Vector3($this->pos5["x"]+0.5,$this->pos5["y"],$this->pos5["z"]+0.5);
			$this->pos6=new Vector3($this->pos6["x"]+0.5,$this->pos6["y"],$this->pos6["z"]+0.5);
			$this->pos7=new Vector3($this->pos7["x"]+0.5,$this->pos7["y"],$this->pos7["z"]+0.5);
			$this->pos8=new Vector3($this->pos8["x"]+0.5,$this->pos8["y"],$this->pos8["z"]+0.5);
			$this->lastpos=new Vector3($this->lastpos["x"]+0.5,$this->lastpos["y"],$this->lastpos["z"]+0.5);
		}
		if(!$this->config->exists("endTime"))
		{
			$this->config->set("endTime",180);
		}
		if(!$this->config->exists("gameTime"))
		{
			$this->config->set("gameTime",300);
		}
		if(!$this->config->exists("waitTime"))
		{
			$this->config->set("waitTime",180);
		}
		if(!$this->config->exists("godTime"))
		{
			$this->config->set("godTime",15);
		}
		$this->endTime=(int)$this->config->get("endTime");//游戏时间
		$this->gameTime=(int)$this->config->get("gameTime");//游戏时间
		$this->waitTime=(int)$this->config->get("waitTime");//等待时间
		$this->godTime=(int)$this->config->get("godTime");//无敌时间
		$this->gameStatus=0;//当前状态
		$this->lastTime=0;//还没开始
		$this->players=array();//加入游戏的玩家
		$this->SetStatus=array();//设置状态
		$this->all=0;//最大玩家数量
		$this->config->save();
		$this->getServer()->getLogger()->info(TextFormat::GREEN."[FSurvivalGame] 欢迎使用本插件，插件加载完毕");
		$this->getServer()->getLogger()->info(TextFormat::GREEN."[FSurvivalGame] 如果觉得好用可以给支付宝FENGberd@qq.com汇款任意数额来支持作者哦");
		$this->getServer()->getLogger()->info(TextFormat::RED."[FSurvivalGame] 本插件完全免费，如果你花了哪怕是一分钱来购买，我建议你多去我的世界pe吧逛逛");
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args)
	{
		if($command->getName()=="lobby")
		{
			if($this->gameStatus>=2)
			{
				$sender->sendMessage("[游戏系统] [饥饿游戏] 游戏已开始 ,不能退出");
				return;
			}
			if(isset($this->players[$sender->getName()]))
			{	
				unset($this->players[$sender->getName()]);
				$sender->setLevel($this->signlevel);
				$sender->teleport($this->signlevel->getSpawnLocation());
				$sender->sendMessage("[游戏系统] [饥饿游戏] 成功退出");
				$this->sendToAll("[游戏系统] [饥饿游戏] 玩家".$sender->getName()."退出了游戏");
				$this->changeStatusSign();
				if($this->gameStatus==1 && count($this->players)<2)
				{
					$this->gameStatus=0;
					$this->lastTime=0;
					$this->sendToAll("[游戏系统] [饥饿游戏] 人数不足 ,已停止倒计时");
					/*foreach($this->players as $pl)
					{
						$p=$this->getServer()->getPlayer($pl["id"]);
						$p->setLevel($this->signlevel);
						$p->teleport($this->signlevel->getSpawnLocation());
						unset($p,$pl);
					}*/
				}
			}
			else
			{
				$sender->sendMessage("[游戏系统] [饥饿游戏] 你不在游戏中");
			}
			return true;
		}
		if(!isset($args[0])){unset($sender,$cmd,$label,$args);return false;};
		switch ($args[0])
		{
		case "set":
			if($this->config->exists("lastpos"))
			{
				$sender->sendMessage("[游戏系统] [饥饿游戏] 游戏已经设置,请先删除设置");
			}
			else
			{
				$name=$sender->getName();
				$this->SetStatus[$name]=0;
				$sender->sendMessage("[游戏系统] [饥饿游戏] 已进入设置模式 ,请先设置状态牌子");
			}
			break;
		case "remove":
			$this->config->remove("sign");
			$this->config->remove("pos1");
			$this->config->remove("pos2");
			$this->config->remove("pos3");
			$this->config->remove("pos4");
			$this->config->remove("pos5");
			$this->config->remove("pos6");
			$this->config->remove("pos7");
			$this->config->remove("pos8");
			$this->config->remove("lastpos");
			$this->config->save();
			unset($this->sign,$this->pos1,$this->pos2,$this->pos3,$this->pos4,$this->pos5,$this->pos6,$this->pos7,$this->pos8,$this->lastpos);
			$sender->sendMessage("[游戏系统] [饥饿游戏] 已删除游戏设置");
			break;
		case "start":
			$this->sendToAll("[游戏系统] [饥饿游戏] 已强制开始游戏");
			$this->gameStatus=1;
			$this->lastTime=5;
			break;
		case "reload":
			unset($this->config);
			@mkdir($this->getDataFolder(), 0777, true);
			$this->config=new Config($this->getDataFolder() . "config.yml", Config::YAML, array());
			if($this->config->exists("lastpos"))
			{
				$this->sign=$this->config->get("sign");
				$this->pos1=$this->config->get("pos1");
				$this->pos2=$this->config->get("pos2");
				$this->pos3=$this->config->get("pos3");
				$this->pos4=$this->config->get("pos4");
				$this->pos5=$this->config->get("pos5");
				$this->pos6=$this->config->get("pos6");
				$this->pos7=$this->config->get("pos7");
				$this->pos8=$this->config->get("pos8");
				$this->lastpos=$this->config->get("lastpos");
				$this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);
				$this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
				$this->sign=new Vector3($this->sign["x"],$this->sign["y"],$this->sign["z"]);
				$this->pos1=new Vector3($this->pos1["x"]+0.5,$this->pos1["y"],$this->pos1["z"]+0.5);
				$this->pos2=new Vector3($this->pos2["x"]+0.5,$this->pos2["y"],$this->pos2["z"]+0.5);
				$this->pos3=new Vector3($this->pos3["x"]+0.5,$this->pos3["y"],$this->pos3["z"]+0.5);
				$this->pos4=new Vector3($this->pos4["x"]+0.5,$this->pos4["y"],$this->pos4["z"]+0.5);
				$this->pos5=new Vector3($this->pos5["x"]+0.5,$this->pos5["y"],$this->pos5["z"]+0.5);
				$this->pos6=new Vector3($this->pos6["x"]+0.5,$this->pos6["y"],$this->pos6["z"]+0.5);
				$this->pos7=new Vector3($this->pos7["x"]+0.5,$this->pos7["y"],$this->pos7["z"]+0.5);
				$this->pos8=new Vector3($this->pos8["x"]+0.5,$this->pos8["y"],$this->pos8["z"]+0.5);
				$this->lastpos=new Vector3($this->lastpos["x"]+0.5,$this->lastpos["y"],$this->lastpos["z"]+0.5);
			}
			if(!$this->config->exists("endTime"))
			{
				$this->config->set("endTime",600);
			}
			if(!$this->config->exists("gameTime"))
			{
				$this->config->set("gameTime",300);
			}
			if(!$this->config->exists("waitTime"))
			{
				$this->config->set("waitTime",180);
			}
			if(!$this->config->exists("godTime"))
			{
				$this->config->set("godTime",15);
			}
			$this->endTime=(int)$this->config->get("endTime");//游戏时间
			$this->gameTime=(int)$this->config->get("gameTime");//游戏时间
			$this->waitTime=(int)$this->config->get("waitTime");//等待时间
			$this->godTime=(int)$this->config->get("godTime");//无敌时间
			$this->gameStatus=0;//当前状态
			$this->lastTime=0;//还没开始
			$this->players=array();//加入游戏的玩家
			$this->SetStatus=array();//设置状态
			$this->all=0;//最大玩家数量
			$this->config->save();
			$sender->sendMessage("[游戏系统] [饥饿游戏] 重载完成");
			break;
		default:
			return false;
			break;
		}
		return true;
	}
	
	public function onPlayerRespawn(PlayerRespawnEvent $event){
        $player = $event->getPlayer();
        if($this->config->exists("lastpos"))
        {
			if($player->getLevel()->getFolderName()==$this->level->getFolderName())
			{
				$v3=$this->signlevel->getSpawnLocation();
				$event->setRespawnPosition(new Position($v3->x,$v3->y,$v3->z,$this->signlevel));
			}
		}
		unset($event,$player);
    }
	
	public function onPlace(BlockPlaceEvent $event)
	{
		if(!isset($this->sign))
		{
			return;
		}
		$block=$event->getBlock();
		if($this->PlayerIsInGame($event->getPlayer()->getName()) || $block->getLevel()==$this->level)
		{
			if(!$event->getPlayer()->isOp())
			{
				$event->setCancelled();
			}
		}
		unset($block,$event);
	}
	
	public function onMove(PlayerMoveEvent $event)
	{
		if(!isset($this->sign))
		{
			return;
		}
		if($this->PlayerIsInGame($event->getPlayer()->getName()) && $this->gameStatus<=1)
		{
			if(!$event->getPlayer()->isOp())
			{
				$event->setCancelled();
			}
		}
		unset($event);
	}
	public function onBreak(BlockBreakEvent $event)
	{
		if(!isset($this->sign))
		{
			return;
		}
		$sign=$this->config->get("sign");
		$block=$event->getBlock();
		if($this->PlayerIsInGame($event->getPlayer()->getName()) || ($block->getX()==$sign["x"] && $block->getY()==$sign["y"] && $block->getZ()==$sign["z"] && $block->getLevel()->getFolderName()==$sign["level"]) || $block->getLevel()==$this->level)
		{
			if(!$event->getPlayer()->isOp())
			{
				$event->setCancelled();
			}
		}
		unset($sign,$block,$event);
	}
	
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
	{
		if(!$this->PlayerIsInGame($event->getPlayer()->getName()) || $event->getPlayer()->isOp() || substr($event->getMessage(),0,1)!="/")
		{
			unset($event);
			return;
		}
		switch(strtolower(explode(" ",$event->getMessage())[0]))
		{
		case "/kill":
		case "/lobby":
			
			break;
		default:
			$event->setCancelled();
			$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 游戏进行中 ,请先结束游戏再发送其他指令");
			$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 也可以使用 /kill 或 /lobby 指令退出游戏");
			break;
		}
		unset($event);
	}
	
	public function onDamage(EntityDamageEvent $event)
	{
		$player = $event->getEntity();
		if ($event instanceof EntityDamageByEntityEvent)
		{
        	$player = $event->getEntity();
        	$killer = $event->getDamager();
			if($player instanceof Player && $killer instanceof Player)
			{
		    	if($this->PlayerIsInGame($player->getName()) && ($this->gameStatus==2 || $this->gameStatus==1))
		    	{
		    		$event->setCancelled();
		    	}
		    	if($this->PlayerIsInGame($player->getName()) && !$this->PlayerIsInGame($killer->getName()) && !$killer->isOp())
		    	{
		    		$event->setCancelled();
		    		$killer->sendMessage("[游戏系统] [饥饿游戏] 无关人员严禁干涉游戏");
		    		$killer->kill();
		    	}
		    }
		}
		
		unset($player,$killer,$event);
	}
	
	public function PlayerIsInGame($name)
	{
		return isset($this->players[$name]);
	}
	
	public function PlayerDeath(PlayerDeathEvent $event){
		if($this->gameStatus==3 || $this->gameStatus==4)
		{
			if(isset($this->players[$event->getEntity()->getName()]))
			{
				$this->ClearInv($event->getEntity());
				unset($this->players[$event->getEntity()->getName()]);
				if(count($this->players)>1)
				{
					$this->sendToAll("[游戏系统] [饥饿游戏] 玩家{$event->getEntity()->getName()}死亡");
					$this->sendToAll("[游戏系统] [饥饿游戏] 剩余人数 :".count($this->players));
					$this->sendToAll("[游戏系统] [饥饿游戏] 剩余时间 :".$this->lastTime."秒");
				}
				$this->changeStatusSign();
			}
			
		}
	}
	
	public function sendToAll($msg){
		foreach($this->players as $pl)
		{
			$this->getServer()->getPlayer($pl["id"])->sendMessage($msg);
		}
		$this->getServer()->getLogger()->info($msg);
		unset($pl,$msg);
	}
	
	public function gameTimber(){
		if(!isset($this->lastpos) || $this->lastpos==array())
		{
			return;
		}
		if(!$this->signlevel instanceof Level)
		{
			$this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);
			$this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
			if(!$this->signlevel instanceof Level)
			{
				return;
			}
		}
		$this->changeStatusSign();
		if($this->gameStatus==0)
		{
			$i=0;
			foreach($this->players as $key=>$val)
			{
				$i++;
				$p=$this->getServer()->getPlayer($val["id"]);
				//echo($i."\n");
				$p->setLevel($this->level);
				eval("\$p->teleport(\$this->pos".$i.");");
				unset($p);
			}
		}
		if($this->gameStatus==1)
		{
			$this->lastTime--;
			$i=0;
			foreach($this->players as $key=>$val)
			{
				$i++;
				$p=$this->getServer()->getPlayer($val["id"]);
				//echo($i."\n");
				$p->setLevel($this->level);
				eval("\$p->teleport(\$this->pos".$i.");");
				unset($p);
			}
			switch($this->lastTime)
			{
			case 1:
			case 2:
			case 3:
			case 4:
			case 5:
			case 10:
			//case 20:
			case 30:
				$this->sendToAll("[游戏系统] [饥饿游戏] 游戏还有".$this->lastTime."秒开始");
				break;
			case 60:
				$this->sendToAll("[游戏系统] [饥饿游戏] 游戏还有1分钟开始");
				break;
			case 90:
				$this->sendToAll("[游戏系统] [饥饿游戏] 游戏还有1分30秒开始");
				break;
			case 120:
				$this->sendToAll("[游戏系统] [饥饿游戏] 游戏还有2分钟开始");
				break;
			case 150:
				$this->sendToAll("[游戏系统] [饥饿游戏] 游戏还有2分30秒开始");
				break;
			case 0:
				$this->gameStatus=2;
				$this->sendToAll("[游戏系统] [饥饿游戏] 游戏开始");
				$this->lastTime=$this->godTime;
				$this->resetChest();
				foreach($this->players as $key=>$val)
				{
					$p=$this->getServer()->getPlayer($val["id"]);
					$p->setMaxHealth(25);
					$p->setHealth(25);
					$p->setLevel($this->level);
				}
				$this->all=count($this->players);
				break;
			}
		}
		if($this->gameStatus==2)
		{
			$this->lastTime--;
			if($this->lastTime<=0)
			{
				$this->gameStatus=3;
				$this->sendToAll("[游戏系统] [饥饿游戏] 无敌状态解除");
				$this->lastTime=$this->gameTime;
				$this->resetChest();
			}
		}
		if($this->gameStatus==3 || $this->gameStatus==4)
		{
			if(count($this->players)==1)
			{
				$this->sendToAll("[游戏系统] [饥饿游戏] 恭喜你取得了胜利");
				foreach($this->players as &$pl)
				{
					$p=$this->getServer()->getPlayer($pl["id"]);
					Server::getInstance()->broadcastMessage("[游戏系统] [饥饿游戏] 恭喜玩家".$p->getName()."取得了胜利");
					$p->setLevel($this->signlevel);
					$p->getInventory()->clearAll();
					$p->setMaxHealth(25);
					$p->setHealth(25);
					$p->teleport($this->signlevel->getSpawnLocation());
					unset($pl,$p);
				}
				$this->clearChest();
				$this->players=array();
				$this->gameStatus=0;
				$this->lastTime=0;
				return;
			}
			else if(count($this->players)==0)
			{
				Server::getInstance()->broadcastMessage("[游戏系统] [饥饿游戏] 玩家全部死亡 ,游戏结束");
				$this->gameStatus=0;
				$this->lastTime=0;
				$this->clearChest();
				$this->ClearAllInv();
				return;
			}
		}
		if($this->gameStatus==3)
		{
			$this->lastTime--;
			switch($this->lastTime)
			{
			case 1:
			case 2:
			case 3:
			case 4:
			case 5:
			case 10:
				$this->sendToAll("[游戏系统] [饥饿游戏] 游戏还有".$this->lastTime."秒进入决战状态");
				break;
			case 0:
				$this->sendToAll("[游戏系统] [饥饿游戏] 进入决战状态");
				foreach($this->players as $pl)
				{
					$p=$this->getServer()->getPlayer($pl["id"]);
					$p->setLevel($this->level);
					$p->teleport($this->lastpos);
					unset($p,$pl);
				}
				$this->gameStatus=4;
				$this->lastTime=$this->endTime;
				break;
			}
		}
		if($this->gameStatus==4)
		{
			$this->lastTime--;
			switch($this->lastTime)
			{
			case 1:
			case 2:
			case 3:
			case 4:
			case 5:
			case 10:
			//case 20:
			case 30:
				$this->sendToAll("[游戏系统] [饥饿游戏] 游戏还有".$this->lastTime."秒结束");
				break;
			case 0:
				$this->sendToAll("[游戏系统] [饥饿游戏] 超时 ,游戏结束");
				Server::getInstance()->broadcastMessage("[游戏系统] [饥饿游戏] 游戏超时 ,游戏结束");
				foreach($this->players as $pl)
				{
					$p=$this->getServer()->getPlayer($pl["id"]);
					$p->setLevel($this->signlevel);
					$p->teleport($this->signlevel->getSpawnLocation());
					$p->getInventory()->clearAll();
					$p->setMaxHealth(25);
					$p->setHealth(25);
					unset($p,$pl);
				}
				$this->clearChest();
				//$this->ClearAllInv();
				$this->players=array();
				$this->gameStatus=0;
				$this->lastTime=0;
				break;
			}
		}
		$this->changeStatusSign();
	}
	
	public function getMoney($name){
		return EconomyAPI::getInstance()->myMoney($name);
	}
	
	public function addMoney($name,$money){
		EconomyAPI::getInstance()->addMoney($name,$money);
		unset($name,$money);
	}
	
	public function setMoney($name,$money){
		EconomyAPI::getInstance()->setMoney($name,$money);
		unset($name,$money);
	}
	
	public function resetChest()
	{
		FChestReset::getInstance()->ResetChest();
	}
	
	public function clearChest()
	{
		FChestReset::getInstance()->ClearChest();
	}
	
	public function changeStatusSign()
	{
		if(!isset($this->sign))
		{
			return;
		}
		$sign=$this->signlevel->getTile($this->sign);
		if($sign instanceof Sign)
		{
			switch($this->gameStatus)
			{
			case 0:
				$sign->setText("饥饿游戏","点击加入","玩家数量 :".count($this->players),"");
				break;
			case 1:
				$sign->setText("饥饿游戏","点击加入","玩家数量 :".count($this->players),"开始倒计时 :".$this->lastTime."秒");
				break;
			case 2:
				$sign->setText("饥饿游戏","即将开始","玩家数量 :".count($this->players),"无敌倒计时 :".$this->lastTime."秒");
				break;
			case 3:
				$sign->setText("饥饿游戏","游戏进行中","存活玩家 :".count($this->players)."/{$this->all}","决战倒计时 :".$this->lastTime."秒");
				break;
			case 4:
				$sign->setText("饥饿游戏","决战中","存活玩家 :".count($this->players)."/{$this->all}","剩余时间 :".$this->lastTime."秒");
				break;
			}
		}
		unset($sign);
	}
	public function playerBlockTouch(PlayerInteractEvent $event){
		$player=$event->getPlayer();
		$username=$player->getName();
		$block=$event->getBlock();
		$levelname=$player->getLevel()->getFolderName();
		if(isset($this->SetStatus[$username]))
		{
			switch ($this->SetStatus[$username])
			{
			case 0:
				if($event->getBlock()->getID() != 63 && $event->getBlock()->getID() != 68)
				{
					$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击一个牌子");
					return;
				}
				$this->sign=array(
					"x" =>$block->getX(),
					"y" =>$block->getY(),
					"z" =>$block->getZ(),
					"level" =>$levelname);
				$this->config->set("sign",$this->sign);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 状态牌子已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击出生点1");
				$this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
				$this->sign=new Vector3($this->sign["x"],$this->sign["y"],$this->sign["z"]);
				$this->changeStatusSign();
				break;
			case 1:
				$this->pos1=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos1",$this->pos1);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 出生点1已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击出生点2");
				$this->pos1=new Vector3($this->pos1["x"]+0.5,$this->pos1["y"],$this->pos1["z"]+0.5);
				break;
			case 2:
				 $this->pos2=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos2",$this->pos2);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 出生点2已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击出生点3");
				$this->pos2=new Vector3($this->pos2["x"]+0.5,$this->pos2["y"],$this->pos2["z"]+0.5);
				break;	
			case 3:
				$this->pos3=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos3",$this->pos3);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 出生点3已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击出生点4");
				$this->pos3=new Vector3($this->pos3["x"]+0.5,$this->pos3["y"],$this->pos3["z"]+0.5);
				break;	
			case 4:
				$this->pos4=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos4",$this->pos4);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 出生点4已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击出生点5");
				$this->pos4=new Vector3($this->pos4["x"]+0.5,$this->pos4["y"],$this->pos4["z"]+0.5);
				break;
			case 5:
				$this->pos5=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos5",$this->pos5);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 出生点5已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击出生点6");
				$this->pos5=new Vector3($this->pos5["x"]+0.5,$this->pos5["y"],$this->pos5["z"]+0.5);
				break;
			case 6:
				$this->pos6=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos6",$this->pos6);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 出生点6已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击出生点7");
				$this->pos6=new Vector3($this->pos6["x"]+0.5,$this->pos6["y"],$this->pos6["z"]+0.5);
				break;
			case 7:
				$this->pos7=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos7",$this->pos7);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 出生点7已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击出生点8");
				$this->pos7=new Vector3($this->pos7["x"]+0.5,$this->pos7["y"],$this->pos7["z"]+0.5);
				break;	
			case 8:
				$this->pos8=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos8",$this->pos8);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 出生点8已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 请点击决战传送点");
				$this->pos8=new Vector3($this->pos8["x"]+0.5,$this->pos8["y"],$this->pos8["z"]+0.5);
				break;
			case 9:
				$this->lastpos=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("lastpos",$this->lastpos);
				$this->config->save();
				$this->lastpos=new Vector3($this->lastpos["x"]+0.5,$this->lastpos["y"],$this->lastpos["z"]+0.5);
				unset($this->SetStatus[$username]);
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 决战传送点已设置");
				$player->sendMessage(TextFormat::GREEN."[游戏系统] [饥饿游戏] 全部设置完成 ,可以进行游戏了");
				$this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);					
			}
		}
		else
		{
			$sign=$event->getPlayer()->getLevel()->getTile($event->getBlock());
			if(isset($this->lastpos) && $this->lastpos!=array() && $sign instanceof Sign && $sign->getX()==$this->sign->x && $sign->getY()==$this->sign->y && $sign->getZ()==$this->sign->z && $event->getPlayer()->getLevel()->getFolderName()==$this->config->get("sign")["level"])
			{
				if(!$this->config->exists("lastpos"))
				{
					$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 还没有设置游戏 ,不能加入");
					return;
				}
				if(!$event->getPlayer()->hasPermission("FSurvivalGame.touch.startgame"))
				{
					$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 权限不足 ,不能加入");
					return;
				}
				if(!$event->getPlayer()->isOp())
				{
					$inv=$event->getPlayer()->getInventory();
					for($i=0;$i<$inv->getSize();$i++)
    				{
    					if($inv->getItem($i)->getID()!=0)
    					{
    						$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 请先把背包里的东西收到箱子里再进行游戏");
    						return;
    					}
    				}
    				foreach($inv->getArmorContents() as $i)
    				{
    					if($i->getID()!=0)
    					{
    						$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 请先把衣服脱到箱子里再进行游戏");
    						return;
    					}
    				}
    			}
				if($this->gameStatus==0 || $this->gameStatus==1)
				{
					if(!isset($this->players[$event->getPlayer()->getName()]))
					{
						if(count($this->players)>=6)
						{
							$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 人数已满 ,不能加入");
							return;
						}
						$this->sendToAll("[游戏系统] [饥饿游戏] 玩家".$event->getPlayer()->getName()."加入了游戏");
						$this->players[$event->getPlayer()->getName()]=array("id"=>$event->getPlayer()->getName());
						$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 成功加入游戏");
						if($this->gameStatus==0 && count($this->players)>=2)
						{
							$this->gameStatus=1;
							$this->lastTime=$this->waitTime;
							$this->sendToAll("[游戏系统] [饥饿游戏] 人数达到最低标准 ,开始游戏倒计时");
						}
						if(count($this->players)==8 && $this->gameStatus==1 && $this->lastTime>5)
						{
							$this->sendToAll("[游戏系统] [饥饿游戏] 人数已满 ,即将开始游戏");
							$this->lastTime=5;
						}
						$this->changeStatusSign();
					}
					else
					{
						$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 你已经在游戏中 ,输入 /lobby 可退出");
					}
				}
				else
				{
					$event->getPlayer()->sendMessage("[游戏系统] [饥饿游戏] 游戏进行中 ,不能加入");
				}
			}
		}
	}
	
	public function ClearInv($player)
	{
		if(!$player instanceof Player)
		{
			unset($player);
			return;
		}
		$inv=$player->getInventory();
		if(!$inv instanceof Inventory)
		{
			unset($player,$inv);
			return;
		}
		$inv->clearAll();
		unset($player,$inv);
	}
	
	public function ClearAllInv()
	{
		foreach($this->players as $pl)
		{
			$player=$this->getServer()->getPlayer($pl["id"]);
			if(!$player instanceof Player)
			{
				continue;
			}
			$this->ClearInv($player);
		}
		unset($pl,$player);
	}
	
	public function PlayerQuit(PlayerQuitEvent $event){
		if(isset($this->players[$event->getPlayer()->getName()]))
		{	
			unset($this->players[$event->getPlayer()->getName()]);
			$this->ClearInv($event->getPlayer());
			$this->sendToAll("[游戏系统] [饥饿游戏] 玩家".$event->getPlayer()->getName()."退出了游戏");
			$this->changeStatusSign();
			if($this->gameStatus==1 && count($this->players)<2)
			{
				$this->gameStatus=0;
				$this->lastTime=0;
				$this->sendToAll("[游戏系统] [饥饿游戏] 人数不足 ,已停止倒计时");
				/*foreach($this->players as $pl)
				{
					$p=$this->getServer()->getPlayer($pl["id"]);
					$p->setLevel($this->signlevel);
					$p->teleport($this->signlevel->getSpawnLocation());
					unset($p,$pl);
				}*/
			}
		}
	}
	
	public function onDisable(){
		
	}
}
?>
