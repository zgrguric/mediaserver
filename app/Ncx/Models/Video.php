<?php

namespace Ncx\Models;

//https://github.com/jenssegers/laravel-mongodb
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
// use Jenssegers\Mongodb\Eloquent\SoftDeletes;

class Video extends Eloquent
{

    public $timestamps = false;
    protected $collection = 'video';
    protected $connection = 'media';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    /*protected $fillable = [
    ];*/


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
      //'new' => 'boolean'
      'uids' => 'array'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
      'c_at'
    ];

    /**
     * Boot function for using with User Events
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        //static::creating(function($model){});
        //static::updating(function($model){});
        //static::created(function($model){});
        //static::updated(function($model){});
        static::deleting(function($model){
          //TODO delete attachements if there is any
          //dd('deleting event on message');
        });
    }

    /**
    * (mongodb)Message to (mysql)User Hybrid Relation
    */
    /*public function user()
    {
        return $this->belongsTo('User'); //todo add fromuid
    }*/

    /*
    * Format Datetime to be more user friendly.
    */
    public function getDisplayCAtAttribute()
    {

      if ($this->c_at instanceof \Carbon\Carbon)
      {
        $diff = $this->c_at->diffInHours();
        if($diff < 24)
          return $this->c_at->diffForHumans();
        return $this->c_at->format('d.m.Y. H:i');
      }
      return null;
    }



}
