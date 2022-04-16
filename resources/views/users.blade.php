@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ __('Пользователи') }}</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                            <table id="example" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Имя</th>
                                    <th>Менеджер</th>
                                    <th>Обновление</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($users as $user)
                                <tr>
                                    <td>{{$user->id}}</td>
                                    <td><a href="{{ route('client',$user->id) }}">{{$user->name}}</a></td>
                                    <td><a href="{{ route('user',$user->user->id) }}">{{$user->user->name}}</a></td>
                                    <td>{{date('d-m-Y', strtotime($user->updated_at))}}</td>
                                </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                <tr>
                                    <th>ID</th>
                                    <th>Имя</th>
                                    <th>Менеджер</th>
                                    <th>Обновление</th>
                                </tr>
                                </tfoot>
                            </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            $('#example').DataTable();
        } );
    </script>
@endsection
