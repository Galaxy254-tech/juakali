# Gamification System Integration Guide

## Overview

The JuaKali Lend Gamification System is a comprehensive loyalty and engagement platform designed to encourage positive financial behavior and increase user retention. This guide covers integration, configuration, and best practices for the gamification features.

## System Components

### Core Files
- `includes/gamification-system.php` - Main gamification engine
- `gamification/dashboard.php` - User-facing dashboard
- `api/gamification.php` - REST API endpoints
- `database/gamification-schema.sql` - Database schema
- `cron/gamification-cron.php` - Automated processing

### Key Features
1. **Points System** - Award points for various user actions
2. **Badges & Achievements** - Unlockable rewards and recognition
3. **Levels & Progress** - User progression through tiers
4. **Challenges** - Time-based competitive activities
5. **Rewards Store** - Redeem points for tangible benefits
6. **Leaderboards** - Competitive rankings
7. **Streaks** - Consecutive activity tracking
8. **Real-time Notifications** - Instant feedback and updates

## Database Schema

### Primary Tables
- `user_profiles` - Gamification data per user
- `user_levels` - Level configuration (Bronze, Silver, Gold, Platinum, Diamond)
- `badges` - Available badges and criteria
- `user_badges` - User's earned badges
- `user_points` - Point transaction history
- `challenges` - Challenge definitions
- `challenge_participants` - User participation data
- `rewards` - Redeemable rewards store
- `user_rewards` - User redemption history

## Integration Points

### 1. Loan Application Integration

```php
// When user applies for loan
require_once 'includes/gamification-system.php';

$gamification = new GamificationSystem($userId);
$gamification->awardPoints($userId, 'loan_application', [
    'loan_amount' => $loanAmount,
    'application_time' => time()
]);

// Update challenge progress
$gamification->updateChallengeProgress($userId, 'loan_applications');
```

### 2. Repayment Processing

```php
// When user makes a payment
$gamification = new GamificationSystem($userId);

// Check if payment is on time or early
if ($paymentDate <= $dueDate) {
    if ($paymentDate < $dueDate) {
        $points = $gamification->awardPoints($userId, 'loan_repayment_early', [
            'loan_id' => $loanId,
            'days_early' => $daysEarly
        ]);
    } else {
        $points = $gamification->awardPoints($userId, 'loan_repayment_on_time', [
            'loan_id' => $loanId
        ]);
    }
}

// Update payment-related challenges
$gamification->updateChallengeProgress($userId, 'timely_repayments');
$gamification->updateChallengeProgress($userId, 'perfect_month');
```

### 3. KYC Completion

```php
// When user completes KYC verification
$gamification = new GamificationSystem($userId);
$gamification->awardPoints($userId, 'kyc_verification', [
    'verification_method' => 'document_upload',
    'completion_time' => time()
]);

// Award automatic badge if criteria met
$gamification->checkAndAwardBadges($userId);
```

### 4. User Registration & Referrals

```php
// When new user registers with referral code
if ($referralCode) {
    $referrerId = getReferrerId($referralCode);
    $newUserId = createNewUser($userData);

    $gamification = new GamificationSystem($referrerId);
    $gamification->awardPoints($referrerId, 'referral_success', [
        'referred_user_id' => $newUserId,
        'referral_code' => $referralCode
    ]);

    // Update referral challenge progress
    $gamification->updateChallengeProgress($referrerId, 'referrals');
}
```

### 5. Daily Login Tracking

```php
// On user login
require_once 'includes/gamification-system.php';

$gamification = new GamificationSystem($userId);

// Award daily login points (once per day)
$today = date('Y-m-d');
$lastLogin = getLastLoginDate($userId);

if ($lastLogin !== $today) {
    $gamification->awardPoints($userId, 'daily_login');

    // Update streak (handled by cron job)
    updateUserActivity($userId, 'login');
}
```

## API Integration

### REST Endpoints

#### Get User Profile
```http
GET /api/gamification.php?action=profile
Headers: Authorization: Bearer <token>
```

#### Get Leaderboard
```http
GET /api/gamification.php?action=leaderboard&period=monthly&limit=50
```

#### Join Challenge
```http
POST /api/gamification.php?action=join_challenge
Content-Type: application/json

{
    "challenge_id": 123
}
```

#### Award Points
```http
POST /api/gamification.php?action=award_points
Content-Type: application/json

{
    "action_type": "loan_repayment_on_time",
    "metadata": {
        "loan_id": 456,
        "payment_amount": 5000
    }
}
```

### JavaScript Integration

```javascript
// Award points via AJAX
function awardPoints(actionType, metadata = {}) {
    fetch('/api/gamification.php?action=award_points', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + getAuthToken()
        },
        body: JSON.stringify({
            action_type: actionType,
            metadata: metadata
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showPointsAnimation(data.data.points_awarded);
            updatePointsDisplay();
        }
    });
}

// Get real-time updates via WebSocket
function initializeGamificationSocket() {
    const socket = new WebSocket('wss://yourdomain.com/ws/gamification');

    socket.onmessage = function(event) {
        const data = JSON.parse(event.data);
        handleGamificationUpdate(data);
    };
}
```

## Configuration

### Points Configuration

```php
// In gamification-system.php
private $pointsConfig = [
    'loan_repayment_on_time' => 50,
    'loan_repayment_early' => 75,
    'loan_application' => 10,
    'profile_completion' => 25,
    'kyc_verification' => 100,
    'referral_success' => 200,
    'daily_login' => 5,
    'weekly_streak' => 50,
    'monthly_streak' => 200
];
```

### Badge Configuration

```php
private $badgeConfig = [
    'first_loan' => [
        'name' => 'First Steps',
        'description' => 'Take your first loan',
        'icon' => '🚀',
        'points' => 50
    ],
    'timely_payer' => [
        'name' => 'Punctual Payer',
        'description' => 'Pay 5 loans on time',
        'icon' => '⏰',
        'points' => 100
    ]
];
```

### Level Configuration

```php
private $levelThresholds = [
    1 => ['name' => 'Bronze', 'min_points' => 0, 'benefits' => ['Basic access']],
    2 => ['name' => 'Silver', 'min_points' => 500, 'benefits' => ['Reduced fees']],
    3 => ['name' => 'Gold', 'min_points' => 1500, 'benefits' => ['Lower interest rates']],
    4 => ['name' => 'Platinum', 'min_points' => 3000, 'benefits' => ['Best rates']],
    5 => ['name' => 'Diamond', 'min_points' => 6000, 'benefits' => ['VIP treatment']]
];
```

## Automated Processing

### Cron Job Setup

Add to crontab for daily processing:

```bash
# Run gamification tasks daily at midnight
0 0 * * * /usr/bin/php /path/to/juakali/cron/gamification-cron.php all

# Run streak updates every hour
0 * * * * /usr/bin/php /path/to/juakali/cron/gamification-cron.php daily_rewards

# Process expired challenges weekly
0 0 * * 0 /usr/bin/php /path/to/juakali/cron/gamification-cron.php challenges
```

### Manual Execution

```bash
# Run all tasks
php cron/gamification-cron.php all

# Run specific task
php cron/gamification-cron.php daily_rewards
```

## Best Practices

### 1. Point Balance
- Keep point values meaningful but not inflationary
- Balance reward effort with point value
- Review point economics quarterly

### 2. Badge Design
- Create visually appealing icons
- Write clear, motivating descriptions
- Ensure badges are achievable but challenging

### 3. Challenge Creation
- Mix easy and difficult challenges
- Time-limit challenges for urgency
- Include both individual and community challenges

### 4. User Experience
- Provide immediate feedback for actions
- Use animations and celebrations for achievements
- Make progression visible and satisfying

### 5. Performance Optimization
- Cache leaderboard data
- Use database indexes for queries
- Process batch operations in cron jobs

## Analytics & Reporting

### Key Metrics
- Daily Active Users (DAU)
- Points awarded per day
- Badge completion rates
- Challenge participation
- Level progression
- Streak maintenance

### Reports Available
- User engagement overview
- Popular badges and challenges
- Level distribution
- Redemption patterns
- Retention by gamification activity

### Generating Reports

```php
$gamification = new GamificationSystem();
$analytics = $gamification->getGamificationAnalytics(30); // Last 30 days

// Export to CSV
$report = $gamification->generateGamificationReport('csv');
file_put_contents('gamification_report.csv', $report);
```

## Security Considerations

### Point Fraud Prevention
- Validate all point-earning actions
- Monitor for unusual activity patterns
- Implement rate limiting on point awards
- Audit point transactions regularly

### Data Privacy
- Allow users to opt-out of leaderboards
- Provide data export on request
- Follow GDPR/CCPA guidelines
- Secure API endpoints with authentication

## Troubleshooting

### Common Issues

1. **Points not awarding**
   - Check database connections
   - Verify user exists in user_profiles table
   - Check cron job execution logs

2. **Badges not unlocking**
   - Verify achievement conditions
   - Check badge configuration
   - Review user progress tracking

3. **Leaderboard not updating**
   - Check cache expiration
   - Verify cron job execution
   - Review database indexes

4. **Performance issues**
   - Optimize database queries
   - Implement caching
   - Review cron job execution times

### Debug Mode

Enable debug logging:

```php
// In gamification-system.php
define('GAMIFICATION_DEBUG', true);

if (GAMIFICATION_DEBUG) {
    error_log("Gamification Debug: " . $message);
}
```

## Future Enhancements

### Planned Features
- Mobile app integration
- Social sharing of achievements
- Team-based challenges
- AI-powered personalized challenges
- Integration with external reward systems
- Gamification analytics dashboard for admins

### Extension Points
- Custom achievement types
- Third-party reward integrations
- Advanced challenge mechanics
- Social gamification features

## Support

For technical support or questions about the gamification system:
- Check the system logs in `/logs/gamification-cron.log`
- Review database error logs
- Verify API endpoint responses
- Monitor cron job execution

## Version History

- **v1.0.0** - Initial release with core gamification features
- Points, badges, levels, challenges, rewards
- User dashboard and admin tools
- Automated processing and analytics