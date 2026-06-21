-- Reconcile legacy order statuses with their linked purchase plans.
-- Safe to run repeatedly. Completed/cancelled orders are never changed.

UPDATE orders o
JOIN (
  SELECT
    ppo.order_id,
    MAX(
      CASE pp.status
        WHEN 'received' THEN 3
        WHEN 'closed' THEN 3
        WHEN 'partial_received' THEN 2
        WHEN 'ordered' THEN 2
        WHEN 'draft' THEN 1
        ELSE 0
      END
    ) AS status_rank
  FROM purchase_plan_orders ppo
  JOIN purchase_plans pp ON pp.plan_id = ppo.plan_id
  WHERE pp.status <> 'cancelled'
  GROUP BY ppo.order_id
) linked ON linked.order_id = o.order_id
SET o.status = CASE linked.status_rank
  WHEN 3 THEN 'received'
  WHEN 2 THEN 'ordered'
  WHEN 1 THEN 'confirmed'
  ELSE o.status
END
WHERE o.status NOT IN ('done', 'cancelled');
