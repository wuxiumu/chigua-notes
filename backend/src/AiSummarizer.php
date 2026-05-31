<?php
namespace App;

/**
 * AI 整理（Step2）—— 当前为 mock 占位实现。
 * 真实接入时，把 summarize() 内部替换为对 OpenAI / Claude 的调用即可，
 * 输入输出结构保持不变，上层流程无需改动。
 */
class AiSummarizer
{
    /**
     * @param string $rawTitle 原始标题
     * @param string $rawText  原始内容
     * @return array{title:string,summary:string,body:string,timeline:array,opinions:array}
     */
    public static function summarize(string $rawTitle, string $rawText): array
    {
        // ===== MOCK 开始 =====
        // Prompt 思路（真实接入时使用）：
        // 根据以下内容生成一篇呀呀指南，要求：时间线整理 / 各方观点整理 /
        // 玩家观点总结 / 中立表达 / 输出SEO标题
        $seoTitle = $rawTitle . '？事件来龙去脉、时间线与各方回应全记录';

        $summary = '【AI整理·占位】围绕「' . $rawTitle . '」的争议事件，本文中立梳理事件起因、'
                 . '关键时间线、各方回应与玩家观点，持续更新。';

        $body = "## 事件简介\n" . $summary . "\n\n"
              . "## 起因\n（占位）根据采集到的内容整理事件起因。\n\n"
              . "## 争议点\n（占位）核心争议点整理。\n\n"
              . "## 各方回应\n（占位）官方/当事人/媒体回应整理。\n\n"
              . "## 玩家观点\n（占位）网友与玩家观点中立总结。\n\n"
              . "## 后续发展\n（占位）持续跟进。\n";

        $timeline = [
            ['happened_at' => date('Y-m-d H:i'), 'title' => '事件爆料', 'detail' => '（占位）首次曝光'],
            ['happened_at' => date('Y-m-d H:i'), 'title' => '持续发酵', 'detail' => '（占位）网友热议'],
        ];

        $opinions = [
            ['source' => '微博', 'side' => 'player',   'content' => '（占位）玩家观点 A'],
            ['source' => 'NGA',  'side' => 'player',   'content' => '（占位）玩家观点 B'],
            ['source' => '官方', 'side' => 'official', 'content' => '（占位）官方暂未回应'],
        ];
        // ===== MOCK 结束 =====

        return [
            'title'    => $seoTitle,
            'summary'  => $summary,
            'body'     => $body,
            'timeline' => $timeline,
            'opinions' => $opinions,
        ];
    }
}
