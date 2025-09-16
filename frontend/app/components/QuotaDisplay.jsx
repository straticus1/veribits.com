'use client'

export default function QuotaDisplay({ quota }) {
  const { used, allowance } = quota
  const percentage = Math.round((used / allowance) * 100)

  const getQuotaColor = () => {
    if (percentage >= 90) return 'bg-danger-500'
    if (percentage >= 75) return 'bg-warning-500'
    return 'bg-success-500'
  }

  const getQuotaTextColor = () => {
    if (percentage >= 90) return 'text-danger-600'
    if (percentage >= 75) return 'text-warning-600'
    return 'text-success-600'
  }

  return (
    <div>
      <div className="flex justify-between items-center mb-2">
        <span className="text-sm font-medium text-gray-700">Monthly Quota</span>
        <span className={`text-sm font-medium ${getQuotaTextColor()}`}>
          {used} / {allowance}
        </span>
      </div>

      <div className="w-full bg-gray-200 rounded-full h-2">
        <div
          className={`h-2 rounded-full transition-all duration-300 ${getQuotaColor()}`}
          style={{ width: `${Math.min(percentage, 100)}%` }}
        />
      </div>

      <div className="mt-1 text-xs text-gray-500">
        {allowance - used} verifications remaining
      </div>

      {percentage >= 90 && (
        <div className="mt-2 text-xs text-danger-600 font-medium">
          ⚠️ Quota nearly exhausted - consider upgrading
        </div>
      )}
    </div>
  )
}