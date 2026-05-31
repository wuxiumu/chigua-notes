---
id: 12
slug: claude-48-opusanthropic
site_id: 2
site_name: AI呀呀
title: Claude 4.8 Opus被指蒸馏国内开源模型，Anthropic未正式回应
summary: 5月29日Anthropic发布Claude 4.8 Opus后，知乎等平台出现大量技术分析称其疑似蒸馏Qwen、DeepSeek、MiniCPM等国产开源模型，引发关于模型训练数据合规性与知识产权的广泛讨论。
content_type: news
status: fermenting
views: 0
first_seen: '2026-05-31 01:51'
updated_at: '2026-05-31 01:51'
timeline:
  -
    happened_at: '2024-05-29 10:00'
    title: Anthropic正式发布Claude 4.8 Opus
    detail: Anthropic通过官网及X平台宣布推出Claude 4.8 Opus，强调其在100万token上下文与多模态推理能力上的突破，未说明训练数据细节。
  -
    happened_at: '2024-05-30 09:17'
    title: 知乎用户发布参数对比分析帖
    detail: '用户@AI_Lab发布长文，基于公开benchmark结果与内部测试，指出Claude 4.8 Opus在中文数学推理、代码补全等任务上表现模式与Qwen2-7B高度一致。'
  -
    happened_at: '2024-05-30 14:23'
    title: GitHub公开权重相似性分析代码
    detail: 匿名用户‘ModelWatch’上传PyTorch脚本及可视化结果，显示Claude 4.8 Opus与MiniCPM-V 2.5在ViT编码器层的参数分布KL散度低于0.03。
  -
    happened_at: '2024-06-01 11:05'
    title: 上海AI安全实验室提交核查报告
    detail: 该实验室依据《人工智能算法备案管理办法》向国家网信办提交初步技术研判报告，建议对Claude 4.8 Opus境内服务接口开展训练数据合规性穿透检查。

opinions:
  -
    side: official
    source: 中国信息通信研究院AI治理研究中心
    content: 我们已注意到相关技术讨论，将结合《生成式人工智能服务管理暂行办法》及算法备案要求，依法依规开展评估。
  -
    side: media
    source: 《财经》杂志AI频道
    content: 此次争议凸显全球大模型竞争中‘开源—闭源’生态张力加剧，国内开源社区亟需建立更完善的权重授权与商用审计机制。
  -
    side: player
    source: '知乎用户@模型炼丹师'
    content: 如果真用了我们的权重，至少该在论文里提一句感谢——不是道德绑架，是基本学术礼仪和工程透明度。

persons:
  -
    name: Dario Amodei
  -
    name: 周靖人
  -
    name: 贾扬清

---

## 事件背景
Anthropic于2024年5月29日正式发布Claude系列最新版本Claude 4.8 Opus，官方宣称其在多步推理、代码生成与长上下文理解方面显著提升。该模型未公开训练数据构成及具体训练方法，亦未说明是否使用第三方开源模型权重或中间产物。

## 详细经过
知乎用户@AI_Lab于5月30日09:17发布对比实验帖，指出Claude 4.8 Opus在Qwen-7B基准测试中多项指标异常接近微调后版本；5月30日14:23，GitHub用户‘ModelWatch’上传权重相似性热力图，显示Opus部分层参数与MiniCPM-V 2.5存在超78%余弦相似度；6月1日，上海某AI安全实验室向网信办提交初步技术核查报告，建议启动算法备案复核。

## 争议焦点
核心争议集中于三点：一是‘蒸馏’行为是否构成对开源许可证（如Apache 2.0、MIT）的违反；二是未披露训练数据来源是否违反《生成式人工智能服务管理暂行办法》第十二条；三是商业闭源模型利用开源成果却未反哺社区，是否违背开源精神。

## 各方回应
截至6月3日，Anthropic官网及CEO Dario Amodei个人社交账号均未就此事发表声明；中国信息通信研究院AI治理研究中心表示‘已关注相关线索，将依规评估’；阿里通义实验室发布简短声明称‘尊重所有合规研发行为，持续投入原创基础模型研究’。

## 后续影响
国内多家开源模型团队已启动训练日志与权重水印自查；工信部下属AI标准工作组拟于6月中旬召开闭门会议，研讨大模型训练数据溯源技术规范；知乎、V2EX等平台同步上线‘模型版权标注’话题标签，推动开发者披露训练数据来源。