<?php

declare(strict_types=1);

namespace Terpz710\Sell\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\Plugin;
use pocketmine\player\Player;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;

use Terpz710\Sell\Economy\EconomyManager;

class SellCommand extends Command implements PluginOwned {
    private $plugin;
    private $itemsConfig;
    private $economyManager;

    public function __construct(Plugin $plugin) {
        parent::__construct("sell", "Sell the item you are holding");
        $this->plugin = $plugin;
        $this->setPermission("sell.sell");
        $this->itemsConfig = new Config($this->plugin->getDataFolder() . "items.yml", Config::YAML);
        $this->economyManager = new EconomyManager($plugin);
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used by players.");
            return true;
        }

        $itemInHand = $sender->getInventory()->getItemInHand();

        if ($itemInHand->equals(VanillaItems::AIR())) {
            $sender->sendMessage("§l§c(§f!§c) §r§fYou are not holding any items to sell!");
            return true;
        }

        $sellableItems = $this->itemsConfig->get("items", []);
        $amount = 1;

        if (!empty($args) && is_numeric($args[0])) {
            $amount = (int)$args[0];

            if ($amount <= 0) {
                $sender->sendMessage("§l§c(§f!§c) §r§fPlease specify a positive amount to sell!");
                return true;
            }
        }

        if ($amount > 64) {
            $sender->sendMessage("§l§c(§f!§c) §r§fYou can sell a maximum of 64!");
            return true;
        }

        $found = false;

        foreach ($sellableItems as $itemData) {
            if (is_array($itemData) && isset($itemData["name"]) && is_string($itemData["name"])) {
                $itemName = $itemData["name"];
                $parsedItem = StringToItemParser::getInstance()->parse($itemName);

                if ($itemInHand->equals($parsedItem)) {
                    if ($itemInHand->getCount() >= $amount) {
                        $itemInHand->setCount($itemInHand->getCount() - $amount);
                        $sender->getInventory()->setItemInHand($itemInHand);
                        $found = true;

                        $itemPrice = $this->getItemPrice($itemName);

                        if ($itemPrice > 0) {
                            $totalPrice = $itemPrice * $amount;

                            $this->economyManager->addMoney($sender, $totalPrice, function(bool $success) use ($sender, $amount, $totalPrice) {
                                if ($success) {
                                    $sender->sendMessage("§l§a(§f!§a) §r§fYou have sold §b" . $amount . " §bitems" . "§f for §e$" . $totalPrice . "§f!");
                                } else {
                                    $sender->sendMessage("§l§c(§f!§c) §r§fFailed to add money. Please check your economy plugin configuration.");
                                }
                            });
                        }
                    } else {
                        $sender->sendMessage("§l§c(§f!§c) §r§fYou don't have enough items to sell!");
                        return true;
                    }
                }
            }
        }

        if (!$found) {
            $sender->sendMessage("§l§c(§f!§c) §r§fThis item cannot be sold.");
        }

        return true;
    }

    private function getItemPrice(string $itemName): int {
        $sellableItems = $this->itemsConfig->get("items", []);

        foreach ($sellableItems as $itemData) {
            if (is_array($itemData) && isset($itemData["name"]) && is_string($itemData["name"]) && $itemData["name"] === $itemName) {
                if (isset($itemData["price"]) && is_numeric($itemData["price"])) {
                    return (int)$itemData["price"];
                }
            }
        }

        return 0;
    }
}
