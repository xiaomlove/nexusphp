<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ResourceExchangeRule extends Model {
 protected $fillable=['min_size_gb','max_size_gb','bonus_per_gb_day','tier1_bonus_per_day','tier2_bonus_per_day','tier3_bonus_per_day','enabled'];
 protected $casts=['enabled'=>'boolean','min_size_gb'=>'float','max_size_gb'=>'float'];
}