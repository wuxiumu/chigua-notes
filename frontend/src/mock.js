// ============================================
// 51呀呀 Mock 数据库 — 仅站点配置
//
// 事件数据已迁移至 backend/data/events/*.md
// 每个 md 文件 = 一个事件，frontmatter 存结构化数据
//
// 本文件仅提供 sites/categories 的本地 mock，
// 与 SQLite 中的 sites/categories 表保持一致。
// ============================================

export const mockSites = [
  {
    id: 1,
    slug: 'game',
    name: '🎮 游戏呀呀',
    subdomain: 'game.51chigua.com',
    description: '游戏圈爆料、主播骂战、官方回应、玩家群情。每个大事件都有完整的时间线和多方观点。',
    fullDescription: '游戏呀呀汇聚游戏行业的重大事件：从工作室八卦、主播骂战、官方声明，到玩家集体吐槽。我们不仅记录事件本身，更重要的是呈现事件的完整脉络和各方立场。',
    categories: [
      { id: 1, name: '官方声明' },
      { id: 2, name: '玩家群情' },
      { id: 3, name: '媒体报道' },
      { id: 4, name: '工作室八卦' },
    ]
  },
  {
    id: 2,
    slug: 'ai',
    name: '🤖 AI呀呀',
    subdomain: 'ai.51chigua.com',
    description: 'ChatGPT、Gemini、Claude... 大模型风云榜。一览大模型竞争最前线的每个关键时刻。',
    fullDescription: '跟踪 AI 行业的最新动向：从模型突破、融资消息、到安全争议。OpenAI、Google、Anthropic... 谁在领跑？产业格局如何演变？',
    categories: [
      { id: 5, name: '技术突破' },
      { id: 6, name: '融资动向' },
      { id: 7, name: '政策监管' },
      { id: 8, name: '安全事件' },
    ]
  },
  {
    id: 3,
    slug: 'tech',
    name: '💻 互联网呀呀',
    subdomain: 'tech.51chigua.com',
    description: '互联网巨头内讧、创业新闻、融资故事。硅谷和中关村发生的每一件大事。',
    fullDescription: '记录互联网产业的重大变动：从大厂人事风波、创业融资、到产品争议。BAT、字节、美团... 产业巨头和新兴明星的故事。',
    categories: [
      { id: 9, name: '人事变动' },
      { id: 10, name: '融资信息' },
      { id: 11, name: '产品争议' },
      { id: 12, name: '监管风波' },
    ]
  },
  {
    id: 4,
    slug: 'star',
    name: '⭐ 主播呀呀',
    subdomain: 'star.51chigua.com',
    description: '主播骂战、直播翻车、粉丝群情。直播圈的大瓜和小八卦，一个都不落。',
    fullDescription: '汇聚直播平台的热点事件：主播骂战、直播翻车、粉丝大战、官方处罚。从抖音、B站、小红书到其他直播平台的每个热瓜。',
    categories: [
      { id: 13, name: '主播爆料' },
      { id: 14, name: '直播翻车' },
      { id: 15, name: '粉丝群情' },
      { id: 16, name: '平台处罚' },
    ]
  }
];

export function getSites() { return mockSites; }
export function getSiteBySlug(slug) { return mockSites.find(s => s.slug === slug); }
