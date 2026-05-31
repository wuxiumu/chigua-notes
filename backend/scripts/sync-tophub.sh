#!/bin/bash
# ============================================
# TopHub 热榜历史数据批量同步脚本
#
# 用法:
#   ./scripts/sync-tophub.sh 2026-05-01 2026-05-30    # 同步日期范围
#   ./scripts/sync-tophub.sh 2026-05-01                # 仅同步单天
#   ./scripts/sync-tophub.sh --dry-run 2026-05-01      # 预览模式
#   ./scripts/sync-tophub.sh                           # 默认同步最近7天
# ============================================

cd "$(dirname "$0")/.."

DRY_RUN=""
START_DATE=""
END_DATE=""

for arg in "$@"; do
    if [ "$arg" = "--dry-run" ]; then
        DRY_RUN="--dry-run"
    elif [ -z "$START_DATE" ]; then
        START_DATE="$arg"
    elif [ -z "$END_DATE" ]; then
        END_DATE="$arg"
    fi
done

# 默认值
if [ -z "$START_DATE" ]; then
    START_DATE=$(date -j -v-7d +%Y-%m-%d 2>/dev/null || date -d "7 days ago" +%Y-%m-%d)
fi

if [ -z "$END_DATE" ]; then
    END_DATE=$(date +%Y-%m-%d)
fi

# 验证日期格式
if ! [[ "$START_DATE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
    echo "❌ 开始日期格式错误: $START_DATE (应为 YYYY-MM-DD)"
    exit 1
fi

if ! [[ "$END_DATE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
    echo "❌ 结束日期格式错误: $END_DATE (应为 YYYY-MM-DD)"
    exit 1
fi

# 计算天数
start_ts=$(date -j -f "%Y-%m-%d" "$START_DATE" +%s 2>/dev/null || date -d "$START_DATE" +%s)
end_ts=$(date -j -f "%Y-%m-%d" "$END_DATE" +%s 2>/dev/null || date -d "$END_DATE" +%s)

if [ "$start_ts" -gt "$end_ts" ]; then
    echo "❌ 开始日期不能大于结束日期"
    exit 1
fi

total_days=$(( (end_ts - start_ts) / 86400 + 1 ))

echo "🚀 TopHub 历史数据同步"
echo "   日期范围: $START_DATE → $END_DATE ($total_days 天)"
echo "   模式: $([ -n "$DRY_RUN" ] && echo "🔍 预览" || echo "📝 保存")"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 批量采集
current_ts=$start_ts
day=0

while [ "$current_ts" -le "$end_ts" ]; do
    day=$((day + 1))
    current_date=$(date -j -f "%s" "$current_ts" +%Y-%m-%d 2>/dev/null || date -d "@$current_ts" +%Y-%m-%d)

    echo ""
    echo "📅 [$day/$total_days] $current_date"
    echo "────────────────────────────────────────────"

    php scrapers/tophub-scraper.php --date="$current_date" $DRY_RUN 2>&1 | tail -3

    current_ts=$((current_ts + 86400))

    # 避免请求过快
    if [ "$current_ts" -le "$end_ts" ]; then
        sleep 1
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ 同步完成！"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 显示缓存统计
echo ""
echo "📊 当前缓存统计:"
php scrapers/tophub-scraper.php --stats 2>&1
