<?php

namespace Core;

class NpcMessagingSystem
{
    const TYPE_RETALIATION = 'retaliation';
    const TYPE_WARNING = 'warning';
    const TYPE_REINFORCEMENT = 'reinforcement';

    private static $messages = [
        'Aggressive' => [
            'retaliation' => [
                "You dare attack me? My legions allow no insult to pass unpunished!",
                "Foolish move. Now you will see true power.",
                "Did you think I would sleep through your raid? Prepare for war.",
                "Your village will burn for this insolence!",
                "Blood for blood. My troops are already marching."
            ],
            'warning' => [
                "I see your scouts. Do not test my patience.",
                "Turn back now, or face total annihilation.",
                "My armies are vast. Do not give me a reason to use them.",
                "This is your only warning. Stay away from my lands.",
                "I am watching you. one wrong move and you are destroyed."
            ],
            'reinforcement' => [
                "My troops have arrived. They will hold the line.",
                "I have sent my best warriors. Do not waste their lives.",
                "Reinforcements deployed. We will crush the invaders.",
                "My soldiers stand with you. For glory!",
                "Hold fast. My legion is with you."
            ]
        ],
        'Economic' => [
            'retaliation' => [
                "You disrupt my trade, I disrupt your peace.",
                "War is expensive, but I can afford to destroy you.",
                "I preferred profit, but you chose conflict.",
                "My resources fuel my vengeance.",
                "You will pay for every resource you stole."
            ],
            'warning' => [
                "Conflict is bad for business. Stay away.",
                "I have purchased a formidable defense. Do not test it.",
                "Let us prosper separately. Do not force my hand.",
                "I prefer trade agreements to battle reports. Go away.",
                "My mercenaries are well-paid to defend these lands."
            ],
            'reinforcement' => [
                "Sending supplies and guards to protect our investment.",
                "Reinforcements arriving. Let us protect our assets.",
                "Protecting you ensures my own security. Troops sent.",
                "A stable alliance is a profitable one. I am helping.",
                "My defense forces are at your disposal."
            ]
        ],
        'Balanced' => [
            'retaliation' => [
                "An attack on me is an attack on order. I must respond.",
                "You have broken the peace. I will restore it by force.",
                "Equality is my rule. You struck first, now I strike back.",
                "For every action, there is a reaction. Here is mine.",
                "I sought peace, but I am prepared for war."
            ],
            'warning' => [
                "Maintain your distance, neighbor.",
                "I seek no quarrel, but I will defend my borders.",
                "Do not mistake my silence for weakness.",
                "Let us respect each other's territory.",
                "A wise ruler knows which battles to avoid."
            ],
            'reinforcement' => [
                "Allies must stand together. My troops are yours.",
                "Sending aid as agreed. Stand firm.",
                "We are stronger together. Reinforcements pending.",
                "Balance is restored through mutual defense. Helping you now.",
                "I honor our alliance. Troops dispatched."
            ]
        ],
        // Default / Generic Fallback
        'Normal' => [
            'retaliation' => [
                "You attacked me, so I must attack back!",
                "Prepare to defend yourself!",
                "This means war!",
                "I'm sending my troops to your village.",
                "Why did you attack me? expect a counter-attack!"
            ],
            'warning' => [
                "Stay away from my village!",
                "I don't want trouble, but I have troops.",
                "Please stop scouting me.",
                "I am preparing my defenses.",
                "Warning: Do not attack."
            ],
            'reinforcement' => [
                "I'm sending help!",
                "Reinforcements on the way.",
                "Good luck, ally.",
                "Defending you now.",
                "Troops sent to your village."
            ]
        ]
    ];

    /**
     * Get a random message based on user personality and category
     * 
     * @param string|null $personality (Aggressive, Economic, Balanced, etc)
     * @param string $category (retaliation, warning, reinforcement)
     * @return string
     */
    public static function getMessage($personality, $category)
    {
        // Normalize personality
        $p = $personality ?: 'Normal';
        
        // Map other personalities to closest match if needed, or defaults
        if (!isset(self::$messages[$p])) {
            if ($p == 'Raider') $p = 'Aggressive';
            else if ($p == 'Farmer') $p = 'Economic';
            else if ($p == 'Diplomat' || $p == 'Assassin') $p = 'Balanced';
            else $p = 'Normal';
        }

        if (!isset(self::$messages[$p][$category])) {
            return "I am taking action."; // Ultimate fallback
        }

        $options = self::$messages[$p][$category];
        return $options[array_rand($options)];
    }

    public static function getRetaliationMessage($personality) {
        return self::getMessage($personality, self::TYPE_RETALIATION);
    }

    public static function getWarningMessage($personality) {
        return self::getMessage($personality, self::TYPE_WARNING);
    }

    public static function getReinforcementMessage($personality) {
        return self::getMessage($personality, self::TYPE_REINFORCEMENT);
    }
}
