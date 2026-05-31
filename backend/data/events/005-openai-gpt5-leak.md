---
id: 5
slug: openai-gpt5-leak
site_id: 2
site_name: AI呀呀
title: GPT-5 内测版本遭泄露，性能超预期
summary: 一份疑似GPT-5内测评估报告在GitHub泄露，显示其在推理能力上比预期更强。OpenAI紧急处理，但信息已广泛传播。
content_type: news
status: fermenting
views: 28500
first_seen: '2026-05-29 15:00'
updated_at: '2026-05-30 12:00'
timeline:
  - id: 1
    happened_at: '2026-05-29 15:00'
    title: GitHub仓库被发现
    detail: 安全研究者在GitHub发现"gpt5-bench-results"仓库，包含50多页评估数据
  - id: 2
    happened_at: '2026-05-29 16:30'
    title: 数据真实性引发讨论
    detail: 多位AI研究员交叉验证后认为数据可信度较高，话题迅速传播
  - id: 3
    happened_at: '2026-05-29 17:00'
    title: Hacker News登顶
    detail: 泄露消息登上Hacker News首页第一，讨论超过800条
  - id: 4
    happened_at: '2026-05-29 19:00'
    title: OpenAI紧急下架
    detail: OpenAI发送DMCA通知，GitHub仓库被移除，但信息已广泛传播
  - id: 5
    happened_at: '2026-05-30 08:00'
    title: 市场反应
    detail: Anthropic盘后下跌3%，AI创业公司推迟发布计划等待GPT-5
  - id: 6
    happened_at: '2026-05-30 12:00'
    title: 发布时间猜测
    detail: 业内认为GPT-5可能从Q3提前至6月底发布
opinions:
  - id: 1
    side: official
    source: OpenAI发言人
    content: 我们正在调查此次数据泄露事件。关于GPT-5的任何信息，请关注我们的官方公告。我们不会提前披露未发布产品的细节。
  - id: 2
    side: media
    source: The Information
    content: 如果泄露数据属实，GPT-5的能力将再次拉开与竞争对手的差距。特别是1M token的上下文窗口，这是目前任何公开模型都无法企及的。
  - id: 3
    side: player
    source: GitHub开发者@ai-researcher
    content: 从技术角度看，这份报告的格式、数据来源和测试方法与OpenAI的风格完全一致。我倾向于认为这是真实的。
  - id: 4
    side: player
    source: 知乎AI领域答主@模型观察者
    content: GPT-5的泄露可能对OpenAI是一次公关危机，但对整个AI行业来说，这证明了AGI竞赛正在加速。我们可能比预期更早看到通用人工智能的雏形。
persons:
  - id: 13
    name: OpenAI CEO Sam Altman
  - id: 14
    name: OpenAI CTO Mira Murati
  - id: 15
    name: 匿名泄露源研究者
---

## 泄露源头

5月29日下午，GitHub上一个名为"gpt5-bench-results"的公开仓库被发现，其中包含超过50页的性能评估数据。报告显示，GPT-5在多项关键基准测试中表现远超GPT-4o：

- **数学推理**: GSM8K得分98.2%，相比GPT-4o的92.1%有显著提升
- **代码生成**: HumanEval满分率达96.3%，在Python和Rust上表现尤为突出
- **长上下文**: 1M token窗口下的信息检索准确率保持在94%以上
- **多模态**: 图像理解和视频分析能力被描述为"革命性进步"

## 真实性验证

多位AI研究员对泄露数据进行了交叉验证：

- 报告中提及的训练参数量与OpenAI此前公开的技术路线图一致
- 基准测试方法与OpenAI研究团队发表论文中描述的框架高度吻合
- 一位匿名AI实验室研究员确认数据"看起来是真的"

## OpenAI紧急应对

泄露发生后4小时内：

- OpenAI向GitHub发送DMCA下架通知，仓库已被移除
- OpenAI发言人表示"正在调查此次数据泄露事件"
- 但相关信息已被广泛传播，Hacker News、Twitter、Reddit均有大量讨论

## 行业影响分析

泄露消息对AI行业产生连锁反应：

- Anthropic股价盘后下跌3%，市场担忧竞争格局变化
- 多家AI创业公司推迟发布计划，等待GPT-5正式亮相
- 有投资机构上调AI板块整体评级，认为GPT-5将推动新一轮应用爆发

## 社区讨论

开发者社区对泄露消息反应热烈：

- Reddit r/MachineLearning 相关帖文24小时内获得超2万点赞
- Twitter上#GPT5Leak话题阅读量突破5亿
- 国内知乎、微博相关讨论同样火爆

## 后续发展

业内人士普遍认为，此次泄露可能迫使OpenAI提前发布GPT-5。此前预计的Q3发布时间表可能压缩至6月底。
